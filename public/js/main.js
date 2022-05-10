function showImg(id) {
    $('#id' + id).css('display', 'table');
}

function hideImg (id){
    $('#id' + id).css('display', 'none');
}

function selectAllItems(checkboxObjects)
{
    for (let checkboxObject of checkboxObjects) {
        checkboxObject.checked = true;
    }
}
function unselectAllItems(checkboxObjects)
{
    for (let checkboxObject of checkboxObjects) {
        checkboxObject.checked = false;
    }
}
function refreshCartCount(checkboxObjects)
{
    var checkedObjects = count(checkboxObjects);
    document.getElementById("cart-count").innerHTML = "(" + checkedObjects + ")";
}

var selectAll = document.getElementById("select-all");
selectAll.checked = false;
