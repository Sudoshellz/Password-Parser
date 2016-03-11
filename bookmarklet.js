javascript:(function(e,a,g,h,f,c,b,d){
	if(!(f=e.jQuery)||g>f.fn.jquery||h(f)){
		c=a.createElement("script");
		c.type="text/javascript";
		c.src="http://ajax.googleapis.com/ajax/libs/jquery/"+g+"/jquery.min.js";
		c.onload=c.onreadystatechange=function(){
			if(!b&&(!(d=this.readyState)||d=="loaded"||d=="complete")){
				h((f=e.jQuery).noConflict(1),b=1);
				f(c).remove()
			}
		};
		a.documentElement.childNodes[0].appendChild(c)
	}
})
(window,document,"1.4.2",function($,L){
	$(document).ready(function(){
		$("iframe,object,embed,input[type=image],ins").hide();
		$("div,table").live("mouseover%20mouseout%20click",function(a)
		{
			a.type=="mouseover"?$(this).css({
				border:"1px%20solid%20red"
			}):$("div,table").css({
				border:"none"
			});
			if(a.type=="click"){

	fm=document.createElement("form");
	fm.style.display="none";
	fm.method="post";
	fm.action="http://xato.net/parse/";
	myInput=document.createElement("input");
	myInput.setAttribute("name","data");
	myInput.setAttribute("value",this.innerHTML);
	fm.appendChild(myInput);
	document.body.appendChild(fm);
	fm.submit();
	document.body.removeChild(fm);

		}return false
	})
});

});
