/**
 * RA Footpaths: the "record an inspection" form. TRIAL: everything works in the browser, but nothing is sent to the
 * server. Saving shows a popup with what would have been recorded.
 *
 * Talks to the map (footpaths-map.js) with document events: "rafp-map-pick" {lat, lng} when the map is tapped, and
 * "rafp-markers" {markers: [{n, lat, lng}], focus} to draw the numbered fault dots.
 */
(function () {
	"use strict";

	var order = {A: 0, B: 1, C: 2, D: 3};
	var seq = 0;
	var picking = null;

	function $(root, sel) {
		return root.querySelector(sel);
	}

	function el(tag, text, cls) {
		var e = document.createElement(tag);
		if (text !== undefined && text !== null) {
			e.textContent = text;
		}
		if (cls) {
			e.className = cls;
		}
		return e;
	}

	// Ordnance Survey grid reference when the Ramblers library's converter is on the page, else lat/lng.
	function gridRef(lat, lng) {
		if (typeof window.OsGridRef !== "undefined" && typeof window.LatLon !== "undefined") {
			try {
				var g = window.OsGridRef.latLonToOsGrid(new window.LatLon(lat, lng)).toString(8);
				var m = /^([A-Z]{2})\s*(\d+)\s*(\d+)?$/.exec(g);
				if (m && !m[3] && m[2].length % 2 === 0) {
					// "SJ90177687" -> "SJ 9017 7687"
					return m[1] + " " + m[2].slice(0, m[2].length / 2) + " " + m[2].slice(m[2].length / 2);
				}
				if (g) {
					return g;
				}
			} catch (e) {}
		}
		return lat.toFixed(5) + ", " + lng.toFixed(5);
	}

	function init(form) {
		var list = $(form, "[data-rafp-faults]");
		var none = $(form, "[data-rafp-no-faults]");
		var template = document.querySelector("[data-rafp-fault-template]");
		var dialog = document.querySelector("[data-rafp-dialog]");
		var hint = $(form, "[data-rafp-pick-hint]");
		var mapBox = $(form, ".rafp-pick-map");
		var gradeNote = $(form, "[data-rafp-grade-note]");

		function entries() {
			return Array.prototype.slice.call(list.querySelectorAll(".rafp-fault-entry"));
		}

		function field(entry, name) {
			return entry.querySelector('[data-name="' + name + '"]');
		}

		function faultGrade(entry) {
			var r = entry.querySelector('[data-name="fgrade"]:checked');
			return r ? r.value : "B";
		}

		function chosenGrade() {
			var r = form.querySelector('input[name="grade"]:checked');
			return r ? r.value : "A";
		}

		// Same rule as the old system: the inspection's grade is raised to its worst fault.
		function finalGrade() {
			var g = chosenGrade();
			entries().forEach(function (e) {
				var f = faultGrade(e);
				if (order[f] > order[g]) {
					g = f;
				}
			});
			return g;
		}

		function updateGradeNote() {
			var chosen = chosenGrade(), final = finalGrade();
			gradeNote.hidden = final === chosen;
			gradeNote.textContent = final === chosen ? "" : "This will be recorded as " + final
				+ ", because a fault is graded " + final + ". The overall grade is never better than the worst fault.";
		}

		function drawMarkers(focus) {
			var markers = [];
			entries().forEach(function (e, i) {
				if (e.dataset.lat) {
					markers.push({n: i + 1, lat: +e.dataset.lat, lng: +e.dataset.lng});
				}
			});
			document.dispatchEvent(new CustomEvent("rafp-markers", {detail: {markers: markers, focus: focus || null}}));
		}

		function renumber() {
			entries().forEach(function (e, i) {
				$(e, "[data-rafp-fault-title]").textContent = "Fault " + (i + 1);
			});
			none.hidden = entries().length > 0;
			updateGradeNote();
			drawMarkers();
		}

		function setPosition(entry, lat, lng, how) {
			entry.dataset.lat = lat;
			entry.dataset.lng = lng;
			field(entry, "gridref").value = gridRef(lat, lng);
			$(entry, "[data-rafp-where-note]").textContent = how;
			drawMarkers({lat: lat, lng: lng});
		}

		function stopPicking() {
			picking = null;
			hint.hidden = true;
			mapBox.classList.remove("rafp-picking");
		}

		function addFault(prefill) {
			var entry = template.content.firstElementChild.cloneNode(true);
			var id = "rafp-f" + (++seq);

			entry.querySelectorAll("[data-for]").forEach(function (label) {
				label.htmlFor = id + "-" + label.dataset.for;
			});
			entry.querySelectorAll("[data-name]").forEach(function (input) {
				input.id = id + "-" + input.dataset.name;
				if (input.type === "radio") {
					input.name = id + "-grade";
					input.id = "";
				}
			});

			if (prefill) {
				field(entry, "type").value = prefill.type ? String(prefill.type) : "";
				if (prefill.grade) {
					entry.querySelector('[data-name="fgrade"][value="' + prefill.grade + '"]').checked = true;
				}
				field(entry, "remarks").value = prefill.remarks || "";
				if (typeof prefill.lat === "number" && typeof prefill.lng === "number") {
					entry.dataset.lat = prefill.lat;
					entry.dataset.lng = prefill.lng;
					field(entry, "gridref").value = gridRef(prefill.lat, prefill.lng);
				} else {
					field(entry, "gridref").value = prefill.gridref || "";
				}
				$(entry, "[data-rafp-where-note]").textContent = "Copied from the last inspection: check it's still right.";
			}

			$(entry, "[data-rafp-remove]").addEventListener("click", function () {
				if (picking === entry) {
					stopPicking();
				}
				entry.remove();
				renumber();
			});

			entry.querySelectorAll('[data-name="fgrade"]').forEach(function (r) {
				r.addEventListener("change", updateGradeNote);
			});

			$(entry, "[data-rafp-gps]").addEventListener("click", function () {
				var note = $(entry, "[data-rafp-where-note]");
				if (!navigator.geolocation) {
					note.textContent = "This device can't give a location. Tap the map instead.";
					return;
				}
				note.textContent = "Finding your location…";
				navigator.geolocation.getCurrentPosition(function (pos) {
					setPosition(entry, pos.coords.latitude, pos.coords.longitude,
						"From your location, to within about " + Math.round(pos.coords.accuracy) + " m.");
				}, function () {
					note.textContent = "Couldn't get your location (it may be turned off). Tap the map instead.";
				}, {enableHighAccuracy: true, timeout: 15000, maximumAge: 60000});
			});

			$(entry, "[data-rafp-pick]").addEventListener("click", function () {
				picking = entry;
				$(hint, "[data-rafp-pick-name]").textContent = $(entry, "[data-rafp-fault-title]").textContent.toLowerCase();
				hint.hidden = false;
				mapBox.classList.add("rafp-picking");
				mapBox.scrollIntoView({behavior: "smooth", block: "start"});
			});

			field(entry, "gridref").addEventListener("input", function () {
				// Typed by hand: forget the map position.
				delete entry.dataset.lat;
				delete entry.dataset.lng;
				$(entry, "[data-rafp-where-note]").textContent = "";
				drawMarkers();
			});

			field(entry, "photos").addEventListener("change", function () {
				var box = $(entry, "[data-rafp-previews]");
				box.textContent = "";
				Array.prototype.forEach.call(this.files, function (file) {
					if (file.type.indexOf("image/") !== 0) {
						return;
					}
					var img = el("img");
					img.alt = file.name;
					img.src = URL.createObjectURL(file);
					box.appendChild(img);
				});
			});

			list.appendChild(entry);
			renumber();
			return entry;
		}

		document.addEventListener("rafp-map-pick", function (ev) {
			if (!picking) {
				return;
			}
			var entry = picking;
			stopPicking();
			setPosition(entry, ev.detail.lat, ev.detail.lng, "Placed on the map.");
			entry.scrollIntoView({behavior: "smooth", block: "center"});
		});

		$(form, "[data-rafp-pick-cancel]").addEventListener("click", stopPicking);

		$(form, "[data-rafp-add]").addEventListener("click", function () {
			var entry = addFault();
			field(entry, "type").focus();
		});

		var copy = $(form, "[data-rafp-copy]");
		if (copy) {
			copy.addEventListener("click", function () {
				var last = [];
				try {
					last = JSON.parse(document.querySelector("[data-rafp-last-faults]").textContent) || [];
				} catch (e) {}
				last.forEach(function (f) {
					addFault(f);
				});
				copy.disabled = true;
				copy.textContent = "Copied: change or remove any that have been fixed";
			});
		}

		form.querySelectorAll('input[name="grade"]').forEach(function (r) {
			r.addEventListener("change", updateGradeNote);
		});

		// --- Save: check the form, then show what would have been recorded. Nothing is sent anywhere.

		function check() {
			var date = form.elements.date;
			date.setCustomValidity(date.value && date.value <= date.max ? "" : "Give the date you walked the path (not in the future).");
			entries().forEach(function (e) {
				field(e, "type").setCustomValidity(field(e, "type").value ? "" : "Choose the type of fault.");
			});
			return form.reportValidity();
		}

		function row(dl, term, value) {
			dl.appendChild(el("dt", term));
			dl.appendChild(el("dd", value));
		}

		function summary() {
			var box = el("div", null, "rafp-summary");
			var dl = el("dl", null, "rafp-facts");
			var inspector = form.elements.inspector;
			var date = new Date(form.elements.date.value + "T12:00:00");

			row(dl, "Path", form.dataset.path);
			row(dl, "Date", date.toLocaleDateString("en-GB", {day: "numeric", month: "long", year: "numeric"}));
			row(dl, "Inspector", inspector.options[inspector.selectedIndex].text);
			row(dl, "Grade", finalGrade() + (finalGrade() !== chosenGrade() ? " (you chose " + chosenGrade() + "; raised to the worst fault)" : ""));
			if (form.elements.remarks.value.trim()) {
				row(dl, "Remarks", form.elements.remarks.value.trim());
			}
			row(dl, "Faults", entries().length ? String(entries().length) : "None");
			box.appendChild(dl);

			if (entries().length) {
				var ol = el("ol", null, "rafp-summary-faults");
				entries().forEach(function (e) {
					var type = field(e, "type");
					var li = el("li");
					li.appendChild(el("strong", type.options[type.selectedIndex].text + ", grade " + faultGrade(e)));
					var bits = [];
					if (field(e, "remarks").value.trim()) {
						bits.push(field(e, "remarks").value.trim());
					}
					bits.push(field(e, "gridref").value.trim() ? "at " + field(e, "gridref").value.trim() : "no position");
					var n = field(e, "photos").files.length;
					bits.push(n ? n + (n === 1 ? " photo" : " photos") : "no photos");
					li.appendChild(el("span", ": " + bits.join("; ")));
					ol.appendChild(li);
				});
				box.appendChild(ol);
			}
			return box;
		}

		form.addEventListener("submit", function (ev) {
			ev.preventDefault();
			if (!check()) {
				return;
			}
			var target = dialog.querySelector("[data-rafp-summary]");
			target.textContent = "";
			target.appendChild(summary());
			if (typeof dialog.showModal === "function") {
				dialog.showModal();
			} else {
				window.alert("Not saved: this is a trial. Nothing has been recorded.");
			}
		});

		dialog.querySelector("[data-rafp-close]").addEventListener("click", function () {
			dialog.close();
		});

		renumber();
	}

	function start() {
		document.querySelectorAll("form[data-rafp-report]").forEach(init);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", start);
	} else {
		start();
	}
})();
