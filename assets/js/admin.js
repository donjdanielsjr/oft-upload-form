(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		if (window.jQuery && window.jQuery.fn && window.jQuery.fn.wpColorPicker) {
			window.jQuery('.oftuf-color-picker').wpColorPicker();
		}

		var selectAll = document.querySelector('.oftuf-select-all');
		var checkboxes = document.querySelectorAll('.oftuf-submission-checkbox');

		if (!selectAll || !checkboxes.length) {
			return;
		}

		selectAll.addEventListener('change', function () {
			checkboxes.forEach(function (checkbox) {
				checkbox.checked = selectAll.checked;
			});
		});
	});
}());

