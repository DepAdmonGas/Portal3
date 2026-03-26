function menuApp() {
return {
menus: [],

init() {
this.cargarMenus();
},

cargarMenus() {
fetch('/menu')
.then(res => res.json())
.then(data => {
this.menus = this.buildTree(data);
});
},

buildTree(data) {
return data.map(grupo => {
let map = {};
let items = [];

grupo.items.forEach(item => {
item.children = [];
item.open = false;
map[item.id] = item;
});

grupo.items.forEach(item => {
if (item.padre_id) {
map[item.padre_id]?.children.push(item);
} else {
items.push(item);
}
});

grupo.items = items;
return grupo;
});
},

toggle(item) {
if (item.children.length > 0) {
item.open = !item.open;
} else {
window.location.href = item.ruta;
}
}
}
}

hljs.initHighlightingOnLoad();
document.querySelectorAll("pre.code-view > code").forEach((codeBlock) => {
codeBlock.textContent = codeBlock.innerHTML;
});