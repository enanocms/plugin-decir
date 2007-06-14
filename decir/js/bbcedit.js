// Client detection from MediaWiki
var clientPC = navigator.userAgent.toLowerCase(); // Get client info
var is_gecko = ((clientPC.indexOf('gecko')!=-1) && (clientPC.indexOf('spoofer')==-1)
                && (clientPC.indexOf('khtml') == -1) && (clientPC.indexOf('netscape/7.0')==-1));
var is_safari = ((clientPC.indexOf('applewebkit')!=-1) && (clientPC.indexOf('spoofer')==-1));
var is_khtml = (navigator.vendor == 'KDE' || ( document.childNodes && !document.all && !navigator.taintEnabled ));
if (clientPC.indexOf('opera') != -1) {
	var is_opera = true;
	var is_opera_preseven = (window.opera && !document.childNodes);
	var is_opera_seven = (window.opera && document.childNodes);
}

var $_GET=new Object();
var aParams=document.location.search.substr(1).split('&');
for ( i = 0; i < aParams.length; i++ ) {
  var aParam=aParams[i].split('=');
  var sParamName=aParam[0];
  var sParamValue=aParam[1];
  $_GET[sParamName]=sParamValue;
}

// List of BBcode buttons

var buttons = [
    {
      'start' : '[b]',
      'end'   : '[/b]',
      'desc'  : 'Bold',
      'style' : { 'fontWeight' : 'bold' }
    },
    {
      'start' : '[i]',
      'end'   : '[/i]',
      'desc'  : 'Italics',
      'style' : { 'fontStyle' : 'italic' }
    },
    {
      'start' : '[u]',
      'end'   : '[/u]',
      'desc'  : 'Underline',
      'style' : { 'textDecoration' : 'underline' }
    },
    {
      'start' : '[color=black]',
      'end'   : '[/color]',
      'custom': true,
      'func'  : function() { openColorPicker(this); },
      'desc'  : 'Color',
      'style' : { 'color' : 'red' }
    },
    {
      'start' : '[size=1]',
      'end'   : '[/size]',
      'custom': true,
      'func'  : function() { openSizePicker(this); },
      'desc'  : 'Size'
    },
    {
      'start' : '[code]',
      'end'   : '[/code]',
      'desc'  : 'Code',
      'style' : { 'fontFamily' : 'courier new, monospace' }
    },
    {
      'start' : '[quote]',
      'end'   : '[/quote]',
      'desc'  : 'Quote'
    }
  ];

// List of valid smilies
var smilies = {
  'O:-)'        : 'face-angel.png',
  'O:)'         : 'face-angel.png',
  'O=)'         : 'face-angel.png',
  ':-)'         : 'face-smile.png',
  ':)'          : 'face-smile.png',
  '=)'          : 'face-smile-big.png',
  ':-('         : 'face-sad.png',
  ':('          : 'face-sad.png',
  ';('          : 'face-sad.png',
  ':-O'         : 'face-surprise.png',
  ';-)'         : 'face-wink.png',
  ';)'          : 'face-wink.png',
  '8-)'         : 'face-glasses.png',
  '8)'          : 'face-glasses.png',
  ':-D'         : 'face-grin.png',
  ':D'          : 'face-grin.png',
  '=D'          : 'face-grin.png',
  ':-*'         : 'face-kiss.png',
  ':*'          : 'face-kiss.png',
  '=*'          : 'face-kiss.png',
  ':\'('        : 'face-crying.png',
  ':-|'         : 'face-plain.png',
  ':-\\'        : 'face-plain.png',
  ':-/'         : 'face-plain.png',
  ':joke:'      : 'face-plain.png',
  ']:->'        : 'face-devil-grin.png',
  ':kiss:'      : 'face-kiss.png',
  ':-P'         : 'face-tongue-out.png',
  ':P'          : 'face-tongue-out.png',
  ':-p'         : 'face-tongue-out.png',
  ':p'          : 'face-tongue-out.png',
  ':-X'         : 'face-sick.png',
  ':X'          : 'face-sick.png',
  ':sick:'      : 'face-sick.png',
  ':-]'         : 'face-oops.png',
  ':]'          : 'face-oops.png',
  ':oops:'      : 'face-oops.png',
  ':-['         : 'face-embarassed.png',
  ':['          : 'face-embarassed.png'
};

function initBBCodeControls()
{
  txtars = getElementsByClassName(document, 'textarea', 'bbcode');
  for ( i = 0; i < txtars.length; i++ )
  {
    convertTextAreaToBBCode(txtars[i]);
  }
}

var smileycache = { 'td' : [], 'img' : [] };

function convertTextAreaToBBCode(txtarea)
{
  var pn = txtarea.parentNode;
  
  var loadingDiv = document.createElement('div');
  loadingDiv.appendChild(document.createTextNode('Initializing editor...'));
  pn.appendChild(loadingDiv);
  
  if(!IE)
  {
  
    var smileybox = document.createElement('div');
    smileybox.style.cssFloat = 'left';   // Mozilla
    smileybox.style.styleFloat = 'left'; // IE
    smileybox.style.marginRight = '10px';
    smileybox.style.maxWidth = '220px';
    smileybox.style.maxHeight = '300px';
    smileybox.style.clip = 'rect(0px,auto,auto,0px)';
    smileybox.style.overflow = 'auto';
    
    var fl = document.createElement('fieldset');
    var lb = document.createElement('legend');
    lb.appendChild(document.createTextNode('Smilies'));
    fl.appendChild(lb);
    var used = [];
    
    var scriptPath = ''; // REMOVE FOR ENANO IMPLEMENTATION!
    
    var frm = document.createElement('form');
    frm.action='javascript:void(0)';
    frm.onsubmit = function(){return false;};
    
    var tbl = document.createElement('table');
    tbl.border = '0';
    tbl.cellspacing = '0';
    tbl.cellpadding = '0';
    tbl.width = '100%';
    
    var tr = document.createElement('tr');
    var tick = -1;
    var apd = false;
    
    for ( var i in smilies )
    {
      apd = false;
      if ( in_array(smilies[i], used) )
        continue;
      used.push(smilies[i]);
      
      tick++;
      if ( tick == 3 )
      {
        tick = 0;
        tbl.appendChild(tr);
        tr = document.createElement('tr');
        apd = true;
      }
      
      var smile = i.replace(/\\/g, '\\\\');
      
      var td = document.createElement('td');
      td.style.textAlign = 'center';
      td.style.padding = '0';
      
      var img = ( IE ) ? new Image() : document.createElement('input');
      img.type = 'image';
      img.className = 'clicksmiley';
      img.src = scriptPath + '/images/smilies/' + smilies[i];
      img.style.cursor = 'pointer';
      img.style.margin = '2px';
      img.onclick = insertSmiley;
      img.title = i;
      img.alt = i;
      if (IE)
      {
        // This IE bug (yet another) is stupid BEYOND reason.
        setTimeout('smileycache.td['+smileycache.td.length+'].appendChild(smileycache.img['+smileycache.img.length+']);', 20);
        smileycache.img[smileycache.img.length] = img;
        smileycache.td[smileycache.td.length] = td;
      }
      else
      {
        td.appendChild(img);
      }
      tr.appendChild(td);
    }
    
    if (!apd)
      tbl.appendChild(tr);
    
    frm.appendChild(tbl);
    fl.appendChild(frm);
    
    smileybox.appendChild(fl);
    pn.insertBefore(smileybox, txtarea);
    
  }
  else
  {
    var div = document.createElement('div');
    var html = '<fieldset style="padding: 10px; display: inline;"><legend>Available smilies:</legend>';
    var c = 0;
    for ( var i in smilies )
    {
      c++;
      html += i + '&nbsp;&nbsp;';
      if ( c == 10 )
      {
        html += '<br />';
        c = 0;
      }
    }
    html += '</fieldset>';
    div.innerHTML = html;
    pn.appendChild(div, txtarea);
  }
  
  var toolbar = document.createElement('div');
  for ( j = 0; j < buttons.length; j++ )
  {
    var btn = document.createElement('input');
    btn.type='button';
    btn.className = 'bbcbutton';
    btn.value = buttons[j].desc;
    if ( buttons[j].custom )
      btn.onclick = buttons[j].func;
    else 
      btn.onclick = BBCodeClickHandler;
    if ( buttons[j].style )
    {
      for ( var k in buttons[j].style )
      {
        btn.style[k] = buttons[j].style[k];
      }
    }
    toolbar.appendChild(btn);
  }
  
  pn.insertBefore(toolbar, txtarea);
  pn.removeChild(loadingDiv);
}

function insertSmiley()
{
  var imgid = this.src;
  imgid = imgid.split('/');
  imgid = imgid[imgid.length-1];
  emot = array_search(imgid, smilies) + ' ';
  var o = this.parentNode.parentNode.parentNode.parentNode.parentNode.parentNode.nextSibling.firstChild;
  formatBBCode(o, emot, "", "");
  return false;
}

function BBCodeClickHandler()
{
  var obj = false;
  for ( i = 0; i < buttons.length; i++ )
  {
    if ( buttons[i]['desc'] == this.value )
    {
      obj = buttons[i];
      break;
    }
  }
  if(!obj)
    return false;
  formatBBCode(this, obj['start'], obj['end'], obj['desc']);
  return true;
}

//
// COLOR PICKER
//

function openColorPicker(parent)
{
  var off = fetch_offset(parent);
  var dim = fetch_dimensions(parent);
  var top = off['top'] + dim['h'] - 1;
  var left = off['left'];
  
  var div = document.createElement('div');
  div.style.border = '1px solid #000000';
  div.style.padding = '10px';
  div.style.position = 'absolute';
  div.style.top = top + 'px';
  div.style.left = left + 'px';
  div.style.backgroundColor = '#ffffff';
  
  var cwheel = document.createElement('div');
  cwheel.id = 'color_wheel';
  
  var cinput = document.createElement('input');
  cinput.size = '7';
  cinput.id = 'color_val';
  cinput.value = '#ff0000';
  
  var btni = document.createElement('input');
  btni.type = 'button';
  btni.value = 'Insert';
  btni.onclick = finishColorPicker;
  
  var btnc = document.createElement('input');
  btnc.type = 'button';
  btnc.value = 'Cancel';
  btnc.onclick = closeColorPicker;
  
  div.appendChild(cwheel);
  div.appendChild(cinput);
  div.appendChild(btni);
  div.appendChild(btnc);
  
  parent.parentNode.appendChild(div);
  
  $jq('#color_wheel').farbtastic('#color_val');
}

function finishColorPicker()
{
  parent = this.parentNode;
  input = parent.getElementsByTagName('input')[0];
  color = input.value;
  formatBBCode(parent, '[color=' + color + ']', '[/color]', 'Colored text');
  parent.parentNode.removeChild(parent);
}

function closeColorPicker()
{
  parent = this.parentNode;
  parent.parentNode.removeChild(parent);
}

//
// SIZE PICKER
//

function openSizePicker(parent)
{
  
  var off = fetch_offset(parent);
  var dim = fetch_dimensions(parent);
  var top = off['top'] + dim['h'] - 1;
  var left = off['left'];
  
  var div = document.createElement('div');
  div.style.border = '1px solid #000000';
  div.style.padding = '3px';
  div.style.position = 'absolute';
  div.style.top = top + 'px';
  div.style.left = left + 'px';
  div.style.backgroundColor = '#ffffff';
  div.style.width = '130px';
  //div.style.maxHeight = '400px';
  div.style.clip = 'rect(0px,auto,auto,0px)';
  div.style.overflow = 'hidden';
  
  var tbl = document.createElement('table');
  tbl.border = '0';
  tbl.cellspacing = '0';
  tbl.cellpadding = '0';
  tbl.style.maxWidth = '75px';
  tbl.style.clip = 'rect(0px,75px,auto,0px)';
  tbl.style.overflow = 'hidden';
  
  var i = 0;
  
  for ( i = 0.5; i <= 4; i=i+0.5 )
  {
    var tr = document.createElement('tr');
    var td = document.createElement('td');
    td.innerHTML = i;
    tr.appendChild(td);
    var td = document.createElement('td');
    td.className = 'sizepick_td';
    td.style.fontSize = i + 'em';
    td.innerHTML = 'The quick brown fox jumps over the lazy dog.';
    td.onclick = function() { sizePickClickHandler(this); }
    tr.appendChild(td);
    tbl.appendChild(tr);
  }
  
  var a = document.createElement('a');
  a.href='#';
  a.onclick = function() { this.parentNode.parentNode.removeChild(this.parentNode); return false; };
  a.appendChild(document.createTextNode('Close size picker'));
  
  div.appendChild(tbl);
  div.appendChild(a);
  parent.parentNode.appendChild(div);
  
}

function sizePickClickHandler(parent)
{
  size = parent.style.fontSize.substr(0, parent.style.fontSize.length - 2);
  formatBBCode(parent.parentNode.parentNode.parentNode, '[size=' + size + ']', '[/size]', 'Large/small text');
  parent.parentNode.parentNode.parentNode.parentNode.removeChild(parent.parentNode.parentNode.parentNode);
}

//
// HTML RENDERER
//

function htmlspecialchars(text)
{
  text = text.replace(/</g, '&lt;');
  text = text.replace(/>/g, '&gt;');
  return text;
}

function render_bbcode(text)
{
  // Smilies
  for(var i in smilies)
  {
    if ( text.indexOf(i) > -1 )
    {
      while ( text.indexOf(i) > -1 )
      {
        text = text.replace(i, '<img alt="' + rawhtmlcode(i) + '" src="/images/smilies/' + smilies[i] + '" />');
      }
    }
  }
  
  // Destroy (X|HT)ML tags
  text = htmlspecialchars(text);
  text = text.replace(/ /g, '&nbsp;');
  
  // Bold text
  text = text.replace(/\[b\]([\w\W]+?)\[\/b\]/g, '<span style="font-weight: bold;">$1</span>');
  
  // Italicized text
  text = text.replace(/\[i\]([\w\W]+?)\[\/i\]/g, '<span style="font-style: italic;">$1</span>');
  
  // Underlined text
  text = text.replace(/\[u\]([\w\W]+?)\[\/u\]/g, '<span style="text-decoration: underline;">$1</span>');
  
  // Quotes
  text = text.replace(/\[quote\]([\w\W]+?)\[\/quote\]/g, '<blockquote>$1</blockquote>');
  
  // Colored text
  text = text.replace(/\[color=#([0-9A-Fa-f]+?)\]([\w\W]*?)\[\/color\]/g, '<span style="color: #$1">$2</span>');
  
  // Sized text
  text = text.replace(/\[size=([0-9\.]+?)\]([\w\W]*?)\[\/size\]/g, '<span style="font-size: $1em">$2</span>');
  
  // Newlines
  var nlre = new RegExp(unescape('%0A'), 'g');
  text = text.replace(nlre, '<br />' + unescape('%0A'));
  
  // Preformatted text
  text = text.replace(/\[code\]([\w\W]+?)\[\/code\]/gi, '<pre class="code">$1</pre>');
  text = text.replace(/<pre class=\"code\">([\s]+)/gi, '<pre class="code">');
  text = text.replace(/([\s]+)<\/pre>/gi, '</pre>');
  
  return text;
}

function rawhtmlcode(text)
{
  var ret = '';
  for ( var i = 0; i < text.length; i++ )
  {
    chr = text.charCodeAt(i);
    chr = '&#' + chr + ';';
    ret += chr;
  }
  return ret;
}

// Preview function
function makePreview(obj)
{
  obj = document.getElementById(obj);
  var bbcode = obj.value;
  var body = document.getElementsByTagName('body')[0];
  var div = document.createElement('div');
  div.style.border = '1px solid #000';
  div.style.padding = '10px';
  div.innerHTML = render_bbcode(bbcode);
  //body.insertBefore(div, body.firstChild);
  body.appendChild(div);
}

function fetch_offset(obj) {
  var left_offset = obj.offsetLeft;
  var top_offset = obj.offsetTop;
  while ((obj = obj.offsetParent) != null) {
    left_offset += obj.offsetLeft;
    top_offset += obj.offsetTop;
  }
  return { 'left' : left_offset, 'top' : top_offset };
}

function fetch_dimensions(o) {
  var w = o.offsetWidth;
  var h = o.offsetHeight;
  return { 'w' : w, 'h' : h };
}

function getElementsByClassName(parent, type, cls) {
  if(!type)
    type = '*';
  if(!parent)
    parent = document;
  ret = new Array();
  el = parent.getElementsByTagName(type);
  for ( var i in el )
  {
    if(el[i].className)
    {
      if(el[i].className.indexOf(' ') > 0)
      {
        classes = el[i].className.split(' ');
      }
      else
      {
        classes = new Array();
        classes.push(el[i].className);
      }
      if ( in_array(cls, classes) )
        ret.push(el[i]);
    }
  }
  return ret;
}

function in_array(needle, haystack)
{
  for( var i in haystack )
  {
    if(haystack[i] == needle)
      return true;
  }
  return false;
}

function array_search(needle, haystack)
{
  for( var i in haystack )
  {
    if(haystack[i] == needle)
      return i;
  }
  return false;
}

document.getElementsByClassName = function(type, cls) {
  return getElementsByClassName(document, type, cls);
}

// Function adapted from MediaWiki/phpBB
function formatBBCode(parent, tagOpen, tagClose, sampleText)
{
  var txtarea = parent.parentNode.nextSibling;
  
  // IE
	if (document.selection  && !is_gecko) {
		var theSelection = document.selection.createRange().text;
		if (!theSelection)
			theSelection=sampleText;
		txtarea.focus();
		if (theSelection.charAt(theSelection.length - 1) == " ") { // exclude ending space char, if any
			theSelection = theSelection.substring(0, theSelection.length - 1);
			document.selection.createRange().text = tagOpen + theSelection + tagClose + " ";
		} else {
			document.selection.createRange().text = tagOpen + theSelection + tagClose;
		}

	// Mozilla
	} else if(txtarea.selectionStart || txtarea.selectionStart == '0') {
		var replaced = false;
		var startPos = txtarea.selectionStart;
		var endPos = txtarea.selectionEnd;
		if (endPos-startPos)
			replaced = true;
		var scrollTop = txtarea.scrollTop;
		var myText = (txtarea.value).substring(startPos, endPos);
		if (!myText)
			myText=sampleText;
		if (myText.charAt(myText.length - 1) == " ") { // exclude ending space char, if any
			subst = tagOpen + myText.substring(0, (myText.length - 1)) + tagClose + " ";
		} else {
			subst = tagOpen + myText + tagClose;
		}
		txtarea.value = txtarea.value.substring(0, startPos) + subst +
			txtarea.value.substring(endPos, txtarea.value.length);
		txtarea.focus();
		//set new selection
		if (replaced) {
			var cPos = startPos+(tagOpen.length+myText.length+tagClose.length);
			txtarea.selectionStart = cPos;
			txtarea.selectionEnd = cPos;
		} else {
			txtarea.selectionStart = startPos+tagOpen.length;
			txtarea.selectionEnd = startPos+tagOpen.length+myText.length;
		}
		txtarea.scrollTop = scrollTop;

	// All other browsers get no toolbar.
	}
	// reposition cursor if possible
	if (txtarea.createTextRange)
		txtarea.caretPos = document.selection.createRange().duplicate();
}

addOnloadHook(initBBCodeControls);

