function selectList(listId) {
	// Get the ul object
	var t = '';
	var oUl = document.getElementById('pastecode-code').firstChild;
	for(var i in oUl.childNodes) {
		var x = oUl.childNodes[i];
		if(x.innerText != undefined) {
			t = t + "\n" + x.innerText;
			//x.focus();
			//x.select();
		}
	}
	obj = document.getElementById('clippyText');
	obj.style.display = '';
	document.getElementById('txtcopy').value = t.toString();
	document.getElementById('txtcopy').focus();
	document.getElementById('txtcopy').select();
	//alert(t);
}
/* Define innerText for Mozilla based browsers */
if((!Object.isUndefined(HTMLElement)) && (HTMLElement.prototype.__defineGetter__ != undefined)) {
	HTMLElement.prototype.__defineGetter__("innerText", function () {
		var r = this.ownerDocument.createRange();
		r.selectNodeContents(this);
		return r.toString();
	}
	);
}

