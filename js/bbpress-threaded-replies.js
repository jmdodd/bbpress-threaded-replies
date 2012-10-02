/*	Source: wp-includes/js/comment-reply.js
	Modifications to deal with reply-specific IDs. */
addReply = {
	moveForm : function(commId, parentId, respondId, postId) {
		var t = this, div, comm = t.I(commId), respond = t.I(respondId), cancel = t.I('cancel-in-reply-to-link'), parent = t.I('inreplyto'), post = t.I('bbp_topic_id');
		t.rmTiny();

		t.respondId = respondId;
		postId = postId || false;

		if ( ! t.I('wp-temp-form-div') ) {
			div = document.createElement('div');
			div.id = 'wp-temp-form-div';
			div.style.display = 'none';
			respond.parentNode.insertBefore(div, respond);
		}

		comm.parentNode.insertBefore(respond, comm.nextSibling);
		if ( post && postId )
			post.value = postId;
		parent.value = parentId;
		cancel.style.display = '';

		cancel.onclick = function() {
			var t = addReply, temp = t.I('wp-temp-form-div'), respond = t.I(t.respondId);
			t.rmTiny();

			if ( ! temp || ! respond )
				return;

			t.I('inreplyto').value = '0';
			temp.parentNode.insertBefore(respond, temp);
			temp.parentNode.removeChild(temp);
			this.style.display = 'none';
			this.onclick = null;
			t.addTiny();
			return false;
		}
		t.addTiny();

		try { t.I('bbp_reply_content').focus(); }
		catch(e) {}

		return false;
	},

	I : function(e) {
		return document.getElementById(e);
	},
	
	rmTiny : function() {
		try {
			tinyMCE.triggerSave();
			tinyMCE.execCommand('mceFocus', false, 'bbp_reply_content');
			tinyMCE.execCommand('mceRemoveControl', false, 'bbp_reply_content');
		} catch(el) {}
	},

	addTiny : function() {
		try { 
			tinyMCE.execCommand('mceAddControl', false, 'bbp_reply_content');
		} catch (e) {}
	}
}
