(function ($, document) {
	$(document).ready(function () {

		function SikshyaTab(tab) {
			this.tab = tab;
			this.list = this.tab.find('ul.tab-nav li');
			this.tab_content = this.tab.find('.tab-content');
			this.tab_content_item = this.tab.find('section.tab-content-item');

			this.initClick();

		}

		SikshyaTab.prototype.initClick = function () {
			let _that = this;
			this.list.find('a').on('click', function (e) {
				e.preventDefault();
				_that.changeTab($(this));
			})

		};
		SikshyaTab.prototype.changeTab = function (node) {

			var tab_id = node.closest('li').attr('data-id');
			this.list.removeClass('active');
			this.tab.find('ul.tab-nav li[data-id="' + tab_id + '"]').addClass('active');
			this.tab_content_item.removeClass('active');
			this.tab_content.find('section.tab-content-item.' + tab_id).addClass('active');

			$('.sikshya_course_active_tab').val(tab_id);
			
		};


		$.fn.sikshyaTabs = function (args) {


			return $(this).each(function () {

				new SikshyaTab($(this));

			});
		};

		$('.sikshya-tabs').sikshyaTabs();
	});
})(jQuery);
