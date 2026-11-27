<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Usuario;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** M01-only projection over the existing, independently authorized M06 SQL reader. */
final class ChecadorM01IncidenceReadService
{
    private const PAGE_SIZE = 100;
    private const MAX_ITEMS = 2000;

    /** @var callable(Usuario,int,array,int,int):array|null */
    private $readPage;
    /** @var callable(Usuario,int):bool|null */
    private $authorizeM01;

    /** Test seams are accepted only for isolated APP_ENV=test regressions. */
    public function __construct(?callable $readPage = null, ?callable $authorizeM01 = null)
    {
        if (($readPage !== null || $authorizeM01 !== null)
            && strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: ''))) !== 'test') {
            throw new \InvalidArgumentException('M01 incidence reader overrides are restricted to isolated tests.');
        }
        $this->readPage = $readPage;
        $this->authorizeM01 = $authorizeM01;
    }

    /** @param list<int> $authorizedLocalityIds */
    public function readForWorker(
        Usuario $actor,
        int $workplaceId,
        string $localDate,
        int $workerId,
        array $authorizedLocalityIds
    ): array {
        if ($workplaceId < 1 || $workerId < 1 || !self::isCivilDate($localDate)
            || !in_array($workplaceId, $authorizedLocalityIds, true)) {
            return self::failure('not_authorized');
        }

        try {
            if ($this->authorizeM01 !== null) {
                if (!(($this->authorizeM01)($actor, $workplaceId))) return self::failure('not_authorized');
            } else {
                $sessionActor = Auth::user();
                if ($sessionActor === null || (int) ($sessionActor->id ?? 0) !== (int) ($actor->id ?? 0)
                    || (ChecadorAccessService::authorize($workplaceId)['allowed'] ?? false) !== true) {
                    return self::failure('not_authorized');
                }
            }
        } catch (Throwable) {
            return self::failure('authorization_unavailable');
        }

        $pageReader = $this->readPage ?? static function (
            Usuario $user,
            int $locality,
            array $filters,
            int $page,
            int $perPage
        ): array {
            // listForWorkplace retains the independent M06 authorizeIncidenceRead ACL.
            return (new ChecadorAdministrativeIncidenceSqlService())->listForWorkplace(
                $user,
                $locality,
                $filters,
                $page,
                $perPage,
                'start_date',
                'asc'
            );
        };
        $filters = ['worker_id' => $workerId, 'start_date' => $localDate, 'end_date' => $localDate];
        $items = [];
        $seen = [];
        $expectedTotal = null;
        $expectedPages = null;

        for ($page = 1; ; $page++) {
            if ($page > 20) return self::failure('pagination_incomplete');
            try {
                $result = $pageReader($actor, $workplaceId, $filters, $page, self::PAGE_SIZE);
            } catch (Throwable) {
                return self::failure('read_unavailable');
            }
            if (($result['ok'] ?? false) !== true || !is_array($result['data'] ?? null)) {
                $error = is_string($result['error'] ?? null) ? $result['error'] : '';
                return self::failure($error === 'permission_denied' ? 'not_authorized' : 'read_unavailable');
            }

            $data = $result['data'];
            $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
            $total = filter_var($pagination['total_records'] ?? null, FILTER_VALIDATE_INT);
            $pages = filter_var($pagination['total_pages'] ?? null, FILTER_VALIDATE_INT);
            $reportedPage = filter_var($pagination['page'] ?? null, FILTER_VALIDATE_INT);
            $reportedPerPage = filter_var($pagination['per_page'] ?? null, FILTER_VALIDATE_INT);
            if ($total === false || $pages === false || $reportedPage === false
                || $reportedPerPage !== self::PAGE_SIZE
                || $total < 0 || $pages < 0 || $reportedPage !== $page
                || $total > self::MAX_ITEMS
                || $pages !== ($total === 0 ? 0 : (int) ceil($total / self::PAGE_SIZE))
                || ($expectedTotal !== null && ($expectedTotal !== $total || $expectedPages !== $pages))) {
                return self::failure('pagination_incomplete');
            }
            $expectedTotal = $total;
            $expectedPages = $pages;
            $pageItems = $data['items'] ?? null;
            if (!is_array($pageItems)) return self::failure('pagination_incomplete');
            foreach ($pageItems as $item) {
                if (!is_array($item)
                    || (int) ($item['worker_id'] ?? 0) !== $workerId
                    || (int) ($item['workplace_id'] ?? 0) !== $workplaceId
                    || !self::isCivilDate((string) ($item['start_date'] ?? ''))
                    || !self::isCivilDate((string) ($item['end_date'] ?? ''))
                || (string) $item['start_date'] > (string) $item['end_date']
                    || (string) $item['start_date'] > $localDate
                    || (string) $item['end_date'] < $localDate
                    || !is_string($item['uuid_incidencia'] ?? null)
                    || !self::isUuid($item['uuid_incidencia'])
                    || !in_array($item['category'] ?? null, ChecadorAdministrativeIncidenceSqlService::CATEGORIES, true)
                    || !is_string($item['current_status'] ?? null)
                    || trim($item['current_status']) === ''
                    || filter_var($item['current_revision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    return self::failure('persisted_data_inconsistent');
                }
                $uuid = strtolower(trim($item['uuid_incidencia']));
                if (isset($seen[$uuid])) return self::failure('persisted_data_inconsistent');
                $seen[$uuid] = true;
                // Do not expose M06 reason, evidence, actors or revision history in M01.
                $items[] = [
                    'uuid_incidencia' => $uuid,
                    'worker_id' => $workerId,
                    'workplace_id' => $workplaceId,
                    'category' => (string) ($item['category'] ?? ''),
                    'date_range' => ['start_date' => (string) $item['start_date'], 'end_date' => (string) $item['end_date']],
                    'current_status' => (string) ($item['current_status'] ?? ''),
                    'current_revision' => (int) ($item['current_revision'] ?? 0),
                ];
            }

            if ($expectedPages === 0) {
                if ($pageItems !== [] || $expectedTotal !== 0) return self::failure('pagination_incomplete');
                break;
            }
            if ($page >= $expectedPages) break;
        }

        if (count($items) !== ($expectedTotal ?? -1)) return self::failure('pagination_incomplete');
        return ['ok' => true, 'data' => ['items' => $items, 'count' => count($items), 'complete' => true]];
    }

    private static function isCivilDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        return $parsed !== false && $parsed->format('Y-m-d') === $date
            && !array_filter(DateTimeImmutable::getLastErrors() ?: []);
    }

    private static function isUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) === 1;
    }

    /** @return array{ok:false,error:array{kind:string}} */
    private static function failure(string $kind): array
    {
        return ['ok' => false, 'error' => ['kind' => $kind]];
    }
}
