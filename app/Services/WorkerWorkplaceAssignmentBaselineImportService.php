<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use DateTimeImmutable;
use DateTimeZone;

/** Controlled append-only initial history import, strictly limited to one authorized locality. */
final class WorkerWorkplaceAssignmentBaselineImportService
{
    public function __construct(
        private readonly WorkerWorkplaceAssignmentBaselinePreviewService $previewService = new WorkerWorkplaceAssignmentBaselinePreviewService(),
        private readonly WorkerWorkplaceAssignmentVersionService $writer = new WorkerWorkplaceAssignmentVersionService(),
        private readonly WorkerWorkplaceAssignmentResolver $resolver = new WorkerWorkplaceAssignmentResolver()
    ) {
    }

    /**
     * Apply the exact scoped preview snapshot under the existing assignment lock and one DB transaction.
     * There is deliberately no global/default scope; caller must provide an authorized canonical locality ID.
     *
     * @return array{inserted:int,already_applied:bool,candidate_count:int,candidate_snapshot_sha256:string,source_snapshot_sha256:string,workplace_id:int}
     */
    public function importFromPreview(
        int $workplaceId,
        int $expectedCandidateCount,
        string $expectedCandidateFingerprint,
        string $expectedSourceFingerprint,
        string $validFromUtc,
        int $recordedBy,
        string $reason,
        callable $afterCommitWhileLocked
    ): array {
        if (!WorkerWorkplaceAssignmentHistoryWriteGate::baselineImportEnabled()) {
            throw new DomainException('Preproduction baseline writes are disabled or the connected database identity is not verified.');
        }
        $this->previewService->assertAuthorizedWorkplace($workplaceId, $recordedBy, requireWrite: true);
        if ($expectedCandidateCount < 0
            || !preg_match('/\A[a-f0-9]{64}\z/D', $expectedCandidateFingerprint)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $expectedSourceFingerprint)) {
            throw new DomainException('A valid scoped preview count and both SHA-256 fingerprints are required.');
        }

        $metadata = $this->previewService->prepareVersionMetadata($validFromUtc, $recordedBy, $reason);
        $effectiveUtc = (new DateTimeImmutable($metadata['valid_from_utc'], new DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:s.u\Z');

        return WorkerWorkplaceAssignmentWriteCoordinator::runExclusive(function () use (
            $workplaceId,
            $expectedCandidateCount,
            $expectedCandidateFingerprint,
            $expectedSourceFingerprint,
            $effectiveUtc,
            $metadata,
            $afterCommitWhileLocked
        ): array {
            $result = Capsule::connection()->transaction(function () use (
                $workplaceId,
                $expectedCandidateCount,
                $expectedCandidateFingerprint,
                $expectedSourceFingerprint,
                $effectiveUtc,
                $metadata
            ): array {
                $snapshot = $this->previewService->readScopedSnapshot(
                    $workplaceId,
                    forUpdate: true,
                    actorId: $metadata['recorded_by'],
                    requireWrite: true
                );
                $sourceFingerprint = $snapshot['source_snapshot_sha256'];
                $candidateRows = $snapshot['candidate_rows']->all();
                $candidateFingerprint = WorkerWorkplaceAssignmentBaselinePreviewService::candidateFingerprint($candidateRows);

                if ($snapshot['counts']['conflicting_assignments'] !== 0) {
                    throw new DomainException('Current active worker/locality conflicts must be reconciled before baseline import.');
                }
                if ($sourceFingerprint !== $expectedSourceFingerprint) {
                    throw new DomainException('The scoped worker/locality source or locality reference changed after preview; refresh the preview.');
                }

                if (!hash_equals($expectedCandidateFingerprint, $candidateFingerprint)
                    || count($candidateRows) !== $expectedCandidateCount) {
                    $alreadyAppliedRows = $this->matchingAlreadyAppliedRows(
                        $workplaceId,
                        $expectedCandidateCount,
                        $expectedCandidateFingerprint,
                        $effectiveUtc,
                        $metadata['recorded_by'],
                        $metadata['reason']
                    );
                    if ($alreadyAppliedRows !== null) {
                        $this->assertCanonicalReadback($alreadyAppliedRows, $workplaceId, $effectiveUtc);
                        return [
                            'inserted' => 0,
                            'already_applied' => true,
                            'candidate_count' => $expectedCandidateCount,
                            'candidate_snapshot_sha256' => $expectedCandidateFingerprint,
                            'source_snapshot_sha256' => $sourceFingerprint,
                            'workplace_id' => $workplaceId,
                        ];
                    }
                    throw new DomainException('The scoped baseline candidate set changed after preview; no versions were inserted.');
                }

                foreach ($candidateRows as $row) {
                    $candidate = $this->previewService->lockAndRevalidateCandidate(
                        (int) $row['worker_id'],
                        $workplaceId,
                        $metadata['recorded_by']
                    );
                    $this->writer->appendVersion(
                        $candidate['worker_id'],
                        $candidate['workplace_id'],
                        $effectiveUtc,
                        null,
                        $metadata['recorded_by'],
                        $metadata['reason']
                    );
                }

                $this->assertCanonicalReadback($candidateRows, $workplaceId, $effectiveUtc);

                return [
                    'inserted' => count($candidateRows),
                    'already_applied' => false,
                    'candidate_count' => $expectedCandidateCount,
                    'candidate_snapshot_sha256' => $candidateFingerprint,
                    'source_snapshot_sha256' => $sourceFingerprint,
                    'workplace_id' => $workplaceId,
                ];
            });

            $afterCommitWhileLocked($result);
            return $result;
        });
    }

    /** @return ?array<int,array{worker_id:int,workplace_id:int}> */
    private function matchingAlreadyAppliedRows(
        int $workplaceId,
        int $expectedCount,
        string $expectedCandidateFingerprint,
        string $effectiveUtc,
        int $actorId,
        string $reason
    ): ?array {
        if ($expectedCount === 0) {
            return null;
        }
        $mapping = $this->previewService->resolvePhysicalStationMapping($workplaceId, forUpdate: true);
        $rows = Capsule::table('op_rh_personal_localidad_version as v')
            ->join('op_rh_personal as p', 'p.id', '=', 'v.worker_id')
            ->where('v.workplace_id', $workplaceId)
            ->where('v.valid_from_utc', str_replace('T', ' ', substr($effectiveUtc, 0, 26)))
            ->whereNull('v.valid_to_utc')
            ->where('v.recorded_by', $actorId)
            ->where('v.reason', $reason)
            ->where('p.estado', 1)
            ->where('p.id_estacion', $workplaceId)
            ->orderBy('v.worker_id')
            ->get(['v.worker_id', 'v.workplace_id']);
        $pairs = $rows->map(static fn(object $row): array => [
            'worker_id' => (int) $row->worker_id,
            'workplace_id' => (int) $row->workplace_id,
        ])->all();

        if (count($pairs) !== $expectedCount
            || WorkerWorkplaceAssignmentBaselinePreviewService::candidateFingerprint($pairs) !== $expectedCandidateFingerprint) {
            return null;
        }
        return $pairs;
    }

    /** @param iterable<array{worker_id:int,workplace_id:int}|object> $rows */
    private function assertCanonicalReadback(iterable $rows, int $workplaceId, string $effectiveUtc): void
    {
        foreach ($rows as $row) {
            $workerId = (int) (is_array($row) ? $row['worker_id'] : $row->worker_id);
            $resolution = $this->resolver->resolveAssignments($workerId, $effectiveUtc);
            if ($resolution['status'] !== 'RESOLVED_SINGLE'
                || count($resolution['assignments']) !== 1
                || (int) $resolution['assignments'][0]['workplace_id'] !== $workplaceId) {
                throw new DomainException('Canonical membership resolver readback did not confirm the imported locality.');
            }
        }
    }
}
