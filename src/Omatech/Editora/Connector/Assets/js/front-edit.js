let editNodes = document.querySelectorAll(".front-edit");
let editNodesRelative = document.querySelectorAll(".front-edit-relative");

function toggleEdit(elem) {
    if(elem.innerText === "Edit mode: ON"){
        elem.innerText = "Edit mode: OFF";
        if(editNodes) {
            editNodes.forEach(node => node.style.display = "none");
            editNodesRelative.forEach(node => node.style.display = "none");
        }
    } else {
        elem.innerText = "Edit mode: ON";
        if(editNodes) {
            editNodes.forEach(node => node.style.display = "block");
            editNodesRelative.forEach(node => node.style.display = "block");
            document.querySelector("#header .menu_nav li a img .edit_img ").style.display = "block";
            document.querySelector(".submenu li a img .edit_img ").style.display = "block";
        }
    }
}

function toggleStart() {
    if(editNodes) {
        editNodes.forEach(node => node.style.display = "none");
        editNodesRelative.forEach(node => node.style.display = "none");
    }
}