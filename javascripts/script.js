/**
 * Poll the current status of the installprocess and hand over the response to
 * the Processdisplay
 */
PollStatus = {

	timerInterval : null,
	lockPolling : false,
	startTs : null,

	/**
	 * Run the backendscripts
	 */
	run : function(progressId, selfaction, nonce, callback) {
		var self = this;
		$('#' + progressId)
				.progressBar(
						1,
						{
							boxImage : 'plugins/DBCleaner/images/progressbar.gif',
							barImage : 'plugins/DBCleaner/images/progressbg_red.gif',
						});
		$('#' + progressId + 'info').html('prepare...');
		var d = new Date();
		this.startTs = d.getTime();
		this.timerInterval = setInterval(function() {
			self.updaterun(progressId, selfaction, nonce, callback);
		}, 1000 * 1);
	},

	abort : function() {
		if (this.timerInterval) {
			clearInterval(this.timerInterval);
		}
		this.timerInterval = 0;
	},

	updaterun : function(progressId, selfaction, nonce, callback) {
		var self = this;

		if (this.lockPolling) {
			return;
		}
		this.lockPolling = true;
		var cachesync = Math.floor((Math.random() * 100000) + 1);

		$.ajax({
					'url' : selfaction + 'progressBar&nonce=' + nonce + '&cb='
							+ cachesync,
					'async' : true,
					'dataType' : 'json',
					'error' : function(data) {
						self.abort();
						self.lockPolling = false;
						alert("Interupted");
					},
					'success' : function(data) {
						// console.log(data);
						self.lockPolling = false;
						if (data.ready) {
							data.progress = 100;
							clearInterval(self.timerInterval);
							callback();
						}
						if (data.progress < 2) {
							data.progress = 2;
						}
						$('#' + progressId)
								.progressBar(
										data.progress,
										{
											boxImage : 'plugins/DBCleaner/images/progressbar.gif',
											barImage : 'plugins/DBCleaner/images/progressbg_green.gif',
										});
						src = data.done + '/' + data.count;
						if (data.done && data.count) {
							var d = new Date();
							var diff = (d.getTime() - self.startTs)
									* data.count / data.done;
							d.setTime(d.getTime() + diff);
							src += '  &nbsp; &nbsp; ETA '
									+ (d.getHours() < 10 ? '0' : '')
									+ d.getHours() + ':'
									+ (d.getMinutes() < 10 ? '0' : '')
									+ d.getMinutes();
						}
						$('#' + progressId + 'info').html(src);
						if (data.error) {
							self.abort();
							$('#' + progressId + 'errortext').html(data.error);
							$('#' + progressId).hide();
							$('#' + progressId + 'error').show();
						}

					}
				});
	},
};

var tabController = {
	/**
	 * Create Tab, set loading spinner
	 */
	init : function() {
		$("#pstabs")
				.tabs(
						{
							load : function(e, ui) {
								$(ui.panel).find(".tab-loading").remove();
							},
							beforeActivate : function(e, ui) {
								var $panel = $(ui.newPanel);
								if ($panel.is(":empty")) {
									$panel.append("<img src='plugins/DBCleaner/images/ajax-loader.gif'/><div class='tab-loading'>Loading...</div>")
								}
							},
						});
	},

	reloadTab : function(tabId, url) {
		// console.log('Load Panel', tabId, url);
		this.silentReloadTab(tabId, url);
		$('#pstabs').tabs('load', tabId);
		$('#pstabs').tabs("option", "active", tabId);
	},

	silentReloadTab : function(tabId, url) {
		$('#lnk-tabs-' + (tabId + 1)).attr('href', url);
		$('#ui-tabs-' + (tabId + 1)).empty();
	},

	reloadFilelist : function() {
		$('#ui-tabs-3').empty();
		$('#pstabs').tabs("option", "active", 2);
	}
};
