/**
 * RA Footpaths: the "record an inspection" form, for a new inspection or changing one (data-rafp-editing).
 * Photos are shrunk here (longest side 2000px, JPEG) before sending: quicker on a weak signal, and the copy has none of
 * the original's EXIF data (GPS position, camera). The form is sent with fetch to task=inspection.save, which answers
 * in JSON: {ok, redirect}, {sameDay: n} (ask, then send again with same_day_ok) or {error}.
 *
 * Talks to the map (footpaths-map.js) with document events: "rafp-map-pick" {lat, lng} when the map is tapped, and
 * "rafp-markers" {markers: [{n, lat, lng}], focus} to draw the numbered fault dots.
 */
(function () {
	"use strict";

	var order = {A: 0, B: 1, C: 2, D: 3};
	var LONG_EDGE = 2000;
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
		var hint = $(form, "[data-rafp-pick-hint]");
		var mapBox = $(form, ".rafp-pick-map");
		var gradeNote = $(form, "[data-rafp-grade-note]");

		function entries() {
			return Array.prototype.slice.call(list.querySelectorAll(".rafp-fault-entry"));
		}

		function field(entry, name) {
			return entry.querySelector('[data-name="' + name + '"]');
		}

		// "" when a fault from the old system had no grade and none has been chosen.
		function faultGrade(entry) {
			var r = entry.querySelector('[data-name="fgrade"]:checked');
			return r ? r.value : "";
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
				if (f && order[f] > order[g]) {
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

		function photoCount(entry) {
			return entry.querySelectorAll("[data-rafp-kept] [data-photo-id]:not(.rafp-removed)").length + entry.rafpFiles.length;
		}

		function photoNote(entry) {
			var n = photoCount(entry), max = +form.dataset.maxPerFault;
			$(entry, "[data-rafp-photo-note]").textContent = n > max ? "Too many photos: at most " + max + " per fault." : "";
		}

		// Photos already saved with this fault: each can be marked for removal (and put back) until the form is saved.
		function showKept(entry, photos) {
			var box = $(entry, "[data-rafp-kept]");
			box.hidden = !photos.length;
			photos.forEach(function (p) {
				var fig = el("figure", null, "rafp-photo");
				fig.dataset.photoId = p.id;
				var img = el("img");
				img.src = p.thumb;
				img.alt = "Saved photo";
				var btn = el("button", "Remove", "btn btn-sm btn-link");
				btn.type = "button";
				btn.addEventListener("click", function () {
					var removed = fig.classList.toggle("rafp-removed");
					btn.textContent = removed ? "Put back" : "Remove";
					photoNote(entry);
					dirty = true;
				});
				fig.appendChild(img);
				fig.appendChild(btn);
				box.appendChild(fig);
			});
		}

		// Photos picked on this visit, not sent yet.
		function showNew(entry) {
			var box = $(entry, "[data-rafp-previews]");
			box.querySelectorAll("img").forEach(function (img) {
				URL.revokeObjectURL(img.src);
			});
			box.textContent = "";
			entry.rafpFiles.forEach(function (file, i) {
				var fig = el("figure", null, "rafp-photo");
				var img = el("img");
				img.alt = file.name;
				img.src = URL.createObjectURL(file);
				var btn = el("button", "Remove", "btn btn-sm btn-link");
				btn.type = "button";
				btn.addEventListener("click", function () {
					entry.rafpFiles.splice(i, 1);
					showNew(entry);
				});
				fig.appendChild(img);
				fig.appendChild(btn);
				box.appendChild(fig);
			});
			photoNote(entry);
		}

		// prefill: a fault to copy from the last inspection, or (with an id) one of the inspection being changed.
		function addFault(prefill) {
			var entry = template.content.firstElementChild.cloneNode(true);
			var id = "rafp-f" + (++seq);
			entry.dataset.key = id;
			entry.rafpFiles = [];

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
				var pick = entry.querySelector('[data-name="fgrade"][value="' + (prefill.grade || "") + '"]');
				if (pick) {
					pick.checked = true;
				} else if (prefill.id) {
					// Old record with no grade (or A): leave it unset rather than quietly making it B.
					entry.querySelectorAll('[data-name="fgrade"]').forEach(function (r) {
						r.checked = false;
					});
				}
				field(entry, "remarks").value = prefill.remarks || "";
				if (typeof prefill.lat === "number" && typeof prefill.lng === "number") {
					entry.dataset.lat = prefill.lat;
					entry.dataset.lng = prefill.lng;
					field(entry, "gridref").value = gridRef(prefill.lat, prefill.lng);
				} else {
					field(entry, "gridref").value = prefill.gridref || "";
				}
				$(entry, "[data-rafp-where-note]").textContent = prefill.id ? "" : "Copied from the last inspection: check it's still right.";
				if (prefill.id) {
					entry.dataset.faultId = prefill.id;
					entry.dataset.notes = prefill.notes || 0;
					showKept(entry, prefill.photos || []);
				}
			}

			$(entry, "[data-rafp-remove]").addEventListener("click", function () {
				var notes = +(entry.dataset.notes || 0);
				if (notes && !window.confirm("This fault has " + notes + (notes === 1 ? " follow-up note" : " follow-up notes")
					+ ". Removing the fault deletes them too when you save. Remove it?")) {
					return;
				}
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

			// Photos are collected here, so a phone can add them one at a time; the input is cleared after each pick.
			field(entry, "photos").addEventListener("change", function () {
				Array.prototype.forEach.call(this.files, function (file) {
					if (file.type && file.type.indexOf("image/") !== 0) {
						return;
					}
					entry.rafpFiles.push(file);
				});
				this.value = "";
				showNew(entry);
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

		// --- Save

		var status = $(form, "[data-rafp-status]");
		var saveButton = $(form, "[data-rafp-save]");
		var dirty = false;

		form.addEventListener("input", function () {
			dirty = true;
		});
		window.addEventListener("beforeunload", function (ev) {
			if (dirty) {
				ev.preventDefault();
				ev.returnValue = "";
			}
		});

		function say(text, kind) {
			status.hidden = !text;
			status.textContent = text || "";
			status.className = "rafp-status" + (kind ? " rafp-status-" + kind : "");
			if (text && kind === "error") {
				status.scrollIntoView({behavior: "smooth", block: "center"});
			}
		}

		function check() {
			var date = form.elements.date;
			date.setCustomValidity(date.value && date.value <= date.max ? "" : "Give the date you walked the path (not in the future).");
			var inspector = form.elements.inspector;
			inspector.setCustomValidity(inspector.value ? "" : "Choose the inspector.");
			var ok = true;
			entries().forEach(function (e) {
				field(e, "type").setCustomValidity(field(e, "type").value ? "" : "Choose the type of fault.");
				if (!faultGrade(e) && !e.dataset.faultId) {
					ok = false;
				}
				if (photoCount(e) > +form.dataset.maxPerFault) {
					ok = false;
					say($(e, "[data-rafp-fault-title]").textContent + ": at most " + form.dataset.maxPerFault + " photos per fault.", "error");
				}
			});
			return form.reportValidity() && ok;
		}

		// A smaller JPEG copy of a photo, or null if this browser can't read it (for example HEIC outside Safari).
		function shrink(file) {
			return new Promise(function (resolve) {
				function draw(source, w, h, done) {
					var scale = Math.min(1, LONG_EDGE / Math.max(w, h));
					var canvas = document.createElement("canvas");
					canvas.width = Math.max(1, Math.round(w * scale));
					canvas.height = Math.max(1, Math.round(h * scale));
					canvas.getContext("2d").drawImage(source, 0, 0, canvas.width, canvas.height);
					done();
					canvas.toBlob(function (blob) {
						resolve(blob);
					}, "image/jpeg", 0.85);
				}

				function viaImage() {
					var url = URL.createObjectURL(file);
					var img = new Image();
					img.onload = function () {
						draw(img, img.naturalWidth, img.naturalHeight, function () {
							URL.revokeObjectURL(url);
						});
					};
					img.onerror = function () {
						URL.revokeObjectURL(url);
						resolve(null);
					};
					img.src = url;
				}

				if (window.createImageBitmap) {
					// "from-image" turns phone photos the right way up, from their EXIF orientation.
					window.createImageBitmap(file, {imageOrientation: "from-image"}).then(function (bmp) {
						draw(bmp, bmp.width, bmp.height, function () {
							if (bmp.close) {
								bmp.close();
							}
						});
					}, viaImage);
				} else {
					viaImage();
				}
			});
		}

		function baseName(name) {
			return (name || "photo").replace(/\.[^.]*$/, "").replace(/[^A-Za-z0-9 _-]/g, "").slice(0, 60) || "photo";
		}

		// Everything to send, photos shrunk. Rejects with a message the user can act on.
		function build(sameDayOk) {
			var data = new FormData();
			var faults = [];
			var jobs = [];
			var count = 0;

			data.append(form.dataset.token, "1");
			data.append("id", form.dataset.inspectionId);
			data.append("path_id", form.dataset.pathId);
			data.append("date", form.elements.date.value);
			data.append("inspector", form.elements.inspector.value);
			data.append("grade", chosenGrade());
			data.append("remarks", form.elements.remarks.value);
			if (sameDayOk) {
				data.append("same_day_ok", "1");
			}

			entries().forEach(function (e) {
				var key = e.dataset.key;
				var keep = [];
				e.querySelectorAll("[data-rafp-kept] [data-photo-id]:not(.rafp-removed)").forEach(function (fig) {
					keep.push(+fig.dataset.photoId);
				});
				faults.push({
					id: +(e.dataset.faultId || 0),
					key: key,
					type: +field(e, "type").value,
					grade: faultGrade(e),
					remarks: field(e, "remarks").value,
					gridref: field(e, "gridref").value,
					lat: e.dataset.lat ? +e.dataset.lat : null,
					lng: e.dataset.lng ? +e.dataset.lng : null,
					keep: keep
				});
				e.rafpFiles.forEach(function (file) {
					count++;
					jobs.push(shrink(file).then(function (blob) {
						if (blob) {
							return {key: key, blob: blob, name: baseName(file.name) + ".jpg"};
						}
						if (/^image\/(jpeg|png|webp)$/.test(file.type)) {
							return {key: key, blob: file, name: file.name};
						}
						throw new Error("This device can't read the photo \"" + file.name + "\". Please choose a JPEG instead.");
					}));
				});
			});

			if (count > +form.dataset.maxUploads) {
				return Promise.reject(new Error("That's " + count + " new photos; the site takes at most " + form.dataset.maxUploads
					+ " in one go. Save with fewer, then add the rest by changing the inspection."));
			}

			data.append("faults", JSON.stringify(faults));

			return Promise.all(jobs).then(function (photos) {
				var size = 0;
				photos.forEach(function (p) {
					size += p.blob.size;
					data.append("photos[" + p.key + "][]", p.blob, p.name);
				});
				if (+form.dataset.maxPost && size > +form.dataset.maxPost) {
					throw new Error("The photos are too large to send together. Save with fewer, then add the rest by changing the inspection.");
				}
				return data;
			});
		}

		function send(sameDayOk) {
			saveButton.disabled = true;
			say(entries().some(function (e) {
				return e.rafpFiles.length;
			}) ? "Preparing the photos and saving…" : "Saving…", "busy");

			build(sameDayOk).then(function (data) {
				return fetch(form.dataset.saveUrl, {method: "POST", body: data, credentials: "same-origin", headers: {"Accept": "application/json"}});
			}).then(function (response) {
				return response.json().catch(function () {
					throw new Error(response.status === 413
						? "The photos are too large to send together. Save with fewer, then add the rest by changing the inspection."
						: "The site didn't answer properly (error " + response.status + "). Nothing was saved. Please try again.");
				});
			}).then(function (reply) {
				if (reply.ok && reply.redirect) {
					dirty = false;
					say("Saved.", "ok");
					window.location.assign(reply.redirect);
					return;
				}
				saveButton.disabled = false;
				if (reply.sameDay) {
					say("");
					if (window.confirm("This path already has " + (reply.sameDay === 1 ? "an inspection" : reply.sameDay + " inspections")
						+ " on that date. Save this one as well?")) {
						send(true);
					}
					return;
				}
				say(reply.error || "Nothing was saved. Please try again.", "error");
			}).catch(function (err) {
				saveButton.disabled = false;
				say(err && err.message && !/fetch|network/i.test(err.message) ? err.message
					: "Couldn't reach the website (no signal?). Nothing was saved. Keep this page open and try again.", "error");
			});
		}

		form.addEventListener("submit", function (ev) {
			ev.preventDefault();
			say("");
			if (!check()) {
				return;
			}
			send(false);
		});

		// Changing an inspection: fill the form in.
		var editing = null;
		try {
			editing = JSON.parse(document.querySelector("[data-rafp-editing]").textContent);
		} catch (e) {}
		if (editing && editing.faults) {
			editing.faults.forEach(function (f) {
				addFault(f);
			});
		}
		dirty = false;

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
