var reorder_state = {
  _: false,
  type: false,
  id: false,
  obj: false,
  over: false
}

function decir_admin_dragforum_start(e)
{
  document.onmouseup = decir_admin_dragforum_close;
  
  reorder_state.obj = this;
  reorder_state.over = this;
  
  reorder_state._ = true;
  if ( $dynano(this).hasClass('decir_forum') )
  {
    reorder_state.type = 'forum';
  }
  else if ( $dynano(this).hasClass('decir_category') )
  {
    alert(this.parentNode.DecirForumID);
    document.getElementById('forum_cat_' + this.parentNode.DecirForumID).lastChild.lastChild.style.borderBottom = '5px solid #000000';
    reorder_state.type = 'category';
  }
  
  document.onmousemove = decir_admin_dragforum_onmouse;
  
  return false;
}

function decir_admin_dragforum_onmouse(e)
{
  setMousePos(e);
  
  // Determine threshold for moving the target
  var nodes = reorder_state.obj.parentNode.parentNode.childNodes;
  var threshold = new Object();
  for ( var i = 0; i < nodes.length; i++ )
  {
    var node = nodes[i];
    if ( node.tagName == 'TR' && node.DecirForumID )
    {
      // This is a row with a forum in it - add to the threshold list.
      var t = $(node).Top();
      var h = $(node).Height();
      threshold[node.DecirForumID] = t + ( h / 2 );
    }
  }
  // Move the bar if mouseY is lower than the threshold for the next cell up or higher (lower on screen) than the next cell down.
  var moveme = false;
  var current_threshold = threshold[ reorder_state.over.parentNode.DecirForumID ];
  var next_threshold = threshold[ parseInt(reorder_state.over.parentNode.DecirForumID) + 1 ];
  if ( !next_threshold )
    next_threshold = -1;
  // window.console.debug('current ', current_threshold, ' next ', next_threshold);
  if ( mouseY < current_threshold )
  {
    var prev_tr = reorder_state.over.parentNode.previousSibling;
    if ( !prev_tr )
      // already at the top
      return false;
    // find the prev_td
    var i = 0;
    var prev_td;
    while ( true )
    {
      prev_td = prev_tr.childNodes[i];
      if ( !prev_td )
        continue;
      if ( prev_td.tagName == 'TD' || i == 10000 )
        break;
      i++;
    }
    if ( prev_td.tagName != 'TD' )
      return false;
    reorder_state.over.style.borderBottom = null;
    reorder_state.over = prev_td;
    reorder_state.over.style.borderBottom = '5px solid #000000';
  }
  else if ( mouseY > next_threshold && next_threshold > -1 )
  {
    var next_tr = reorder_state.over.parentNode.nextSibling;
    if ( !next_tr )
      // already at the top
      return false;
    // find the next_td
    var i = 0;
    var next_td;
    while ( true )
    {
      next_td = next_tr.childNodes[i];
      if ( i >= 100 )
        break;
      if ( !next_td )
      {
        i++;
        continue;
      }
      if ( next_td.tagName == 'TD' || i == 100 )
        break;
      i++;
    }
    if ( !next_td )
      return false;
    if ( next_td.tagName != 'TD' )
      return false;
    reorder_state.over.style.borderBottom = null;
    reorder_state.over = next_td;
    reorder_state.over.style.borderBottom = '5px solid #000000';
  }
}

function decir_admin_dragforum_reset(e)
{
  var cls = ( reorder_state.type == 'forum' ? 'decir_forum' : 'decir_category' ); 
  var forums = getElementsByClassName(document, '*', cls);
  for ( var i = 0; i < forums.length; i++ )
  {
    forums[i].onmousedown = decir_admin_dragforum_start;
    forums[i].style.cursor = 'move';
    forums[i].style.borderBottom = null;
  }
}

function decir_admin_dragforum_close(e)
{
  document.onmousemove = function(e) {
    setMousePos(e);
  }
  document.onmouseup = function() {};
  decir_admin_dragforum_reset(e);
  
  reorder_state._ = false;
  
  // Move the row (or rather copy and delete)
  var newnode = reorder_state.obj.parentNode.cloneNode(true);
  insertAfter(reorder_state.obj.parentNode.parentNode, newnode, reorder_state.over.parentNode);
  reorder_state.obj.parentNode.parentNode.removeChild(reorder_state.obj.parentNode);
  
  // for some reason this needs to be called again in gecko (to reinit some values)
  decir_admin_dragforum_onload();
  
  var idlist = [];
  var forums = getElementsByClassName(document, '*', 'decir_forum');
  for ( var i = 0; i < forums.length; i++ )
  {
    idlist.push(forums[i].parentNode.DecirForumID);
  }
  idlist = implode(',', idlist);
  $('forum_order').object.value = idlist;
}

var decir_admin_dragforum_onload = function(e)
{
  var forums = getElementsByClassName(document, '*', 'decir_forum');
  for ( var i = 0; i < forums.length; i++ )
  {
    forums[i].onmousedown = decir_admin_dragforum_start;
    forums[i].style.cursor = 'move';
    forums[i].parentNode.DecirForumID = forums[i].firstChild.value;
    forums[i].title = 'Click and drag to re-order this forum';
  }
  var forums = getElementsByClassName(document, '*', 'decir_category');
  for ( var i = 0; i < forums.length; i++ )
  {
    //forums[i].onmousedown = decir_admin_dragforum_start;
    //forums[i].style.cursor = 'move';
    //forums[i].parentNode.DecirForumID = forums[i].firstChild.value;
  }
}

addOnloadHook(decir_admin_dragforum_onload);

