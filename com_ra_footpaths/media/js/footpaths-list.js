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

	// Map / List switch on the parishes page. The choice is remembered in this browser.
	function initSwitch() {
		var buttons = document.querySelectorAll("[data-rafp-show]");
		if (!buttons.length) {
			return;
		}

		function show(which, remember) {
			buttons.forEach(function (b) {
				var on = b.dataset.rafpShow === which;
				b.setAttribute("aria-pressed", on ? "true" : "false");
				b.classList.toggle("btn-primary", on);
				b.classList.toggle("btn-outline-secondary", !on);
			});
			document.querySelectorAll("[data-rafp-panel]").forEach(function (p) {
				p.hidden = p.dataset.rafpPanel !== which;
			});
			if (remember) {
				try {
					window.localStorage.setItem("rafp-parishes-view", which);
				} catch (e) {}
			}
			if (which === "map") {
				// A map drawn while hidden has no size: tell it to measure itself and refit.
				document.dispatchEvent(new CustomEvent("rafp-map-shown"));
			}
		}

		buttons.forEach(function (b) {
			b.addEventListener("click", function () { show(b.dataset.rafpShow, true); });
		});

		var start = "map";
		try {
			start = window.localStorage.getItem("rafp-parishes-view") || "map";
		} catch (e) {}
		show(start === "list" ? "list" : "map", false);
	}

	function start() {
		initSwitch();
		init();
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", start);
	} else {
		start();
	}
})();
