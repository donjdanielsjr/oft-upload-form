(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var selectAll = document.querySelector('.luf-select-all');
		var checkboxes = document.querySelectorAll('.luf-submission-checkbox');

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
