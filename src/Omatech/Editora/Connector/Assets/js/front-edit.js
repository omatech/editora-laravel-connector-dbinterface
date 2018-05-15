function toggleEdit(elem){
	if($(elem).text()=="Edit mode: ON"){
		$(elem).text("Edit mode: OFF");
		$(".front-edit").hide();
		$(".front-edit-relative").hide();
	}
	else{
		$(elem).text("Edit mode: ON");
		$(".front-edit").show();
		$(".front-edit-relative").show();
        $("#header .menu_nav li a img .edit_img ").show();
        $(".submenu li a img .edit_img ").show();
	}
}

function toggleStart() {
	$(".front-edit").hide();
	$(".front-edit-relative").hide();
}