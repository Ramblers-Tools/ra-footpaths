/**
 * RA Footpaths: filters for the path and parish tables. A table opts in with data-rafp-filter;
 * its filter controls are the elements with data-rafp-for="<table id>".
 * Rows carry data-search (text), data-grade (A-D or N) and data-due ("1" when not inspected for 12 months).
 */
(function () {
	"use strict";

	function apply(table) {
		var controls = document.querySelectorAll('[data-rafp-for="' + table.id + '"]');
		var search = "", grade = "", due = false;

		controls.forEach(function (c) {
			if (c.name === "search") {
				search = c.value.trim().toLowerCase();
			} else if (c.name === "grade") {
				grade = c.value;
			} else if (c.name === "due") {
				due = c.checked;
			}
		});

		var shown = 0;
		table.querySelectorAll("tbody tr").forEach(function (row) {
			var ok = (!search || (row.dataset.search || "").indexOf(search) !== -1)
				&& (!grade || row.dataset.grade === grade)
				&& (!due || row.dataset.due === "1");
			row.hidden = !ok;
			if (ok) {
				shown++;
			}
		});

		var counter = document.querySelector('[data-rafp-count="' + table.id + '"]');
		if (counter) {
			counter.textContent = shown + " shown";
		}
	}

	function init() {
		document.querySelectorAll("table[data-rafp-filter]").forEach(function (table) {
			document.querySelectorAll('[data-rafp-for="' + table.id + '"]').forEach(function (c) {
				c.addEventListener("input", function () { apply(table); });
				c.addEventListener("change", function () { apply(table); });
			});
			apply(table);
		});
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
