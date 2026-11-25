/**
 * RA Footpaths map: a Ramblers library map (ra.leafletmap) with the parish boundary, paths coloured
 * by their latest grade, and fault positions. Started by ra.bootstrapper with command
 * "ra.display.footpaths". Data comes from MapHelper (PHP).
 */
var L, ra;
if (typeof ra === "undefined") {
	ra = {};
}
if (typeof ra.display === "undefined") {
	ra.display = {};
}

ra.display.footpaths = function (options, data) {
	"use strict";

	var lmap = new ra.leafletmap(document.getElementById(options.divId), options);
	data = data || {};

	// The library's Help tab loads a page from the Ramblers mapping help site, which has nothing for these
	// maps (it shows "you should not have arrived at this page"). Show our own help instead.
	lmap._displayHelp = function (tag) {
		tag.appendChild(helpContent());
	};
	lmap.display();
	var map = lmap.map();

	var grades = [["#2e7d32", "A", "good"], ["#f9a825", "B", "minor problems"], ["#ef6c00", "C", "significant problems"],
		["#c62828", "D", "serious problem"], ["#757575", "N", "never inspected"]];

	function helpContent() {
		var box = document.createElement("div");
		box.className = "rafp-map-help";
		function heading(t) {
			box.appendChild(text("h3", t));
		}
		function list(items) {
			var ul = document.createElement("ul");
			items.forEach(function (i) {
				ul.appendChild(typeof i === "string" ? text("li", i) : i);
			});
			box.appendChild(ul);
		}
		function swatch(colour, label) {
			var li = document.createElement("li");
			var s = document.createElement("span");
			s.className = "rafp-swatch";
			s.style.background = colour;
			li.appendChild(s);
			li.appendChild(document.createTextNode(" " + label));
			return li;
		}

		heading("Using the map");
		list([
			"Drag to move around. Zoom with the mouse wheel, by double-clicking, or with two fingers on a phone.",
			"The layers button (top right of the map) changes the map: OS Explorer/Landranger, street maps or aerial photos.",
			"Settings (next to Help) has the map's own options. Map takes you back to the map."
		]);

		if (data.parishes) {
			heading("This map");
			list([
				"Each shape is one of our parishes. Click one to see its figures and a link to open it.",
				"Parishes you inspect have a thick outline and a dark name label, and the map opens on them.",
				"The map outside our parishes is faded."
			]);
			heading("Shading: paths inspected in the last 12 months");
			list([swatch("#2e7d32", "75% or more"), swatch("#f9a825", "50 to 74%"), swatch("#ef6c00", "25 to 49%"), swatch("#c62828", "under 25%")]);
		} else {
			heading("This map");
			var lines = (data.faults || []).length || (data.paths || []).some(function (p) { return p.current; })
				? ["The path you are looking at is drawn thicker, with a white edge.",
					"Dots with a white ring mark where faults were recorded, coloured by their grade. Click a dot to see what it was and open its report.",
					"Click any path to see its last inspection and a link to its history."]
				: ["Each line is a path, coloured by the grade at its latest inspection.",
					"Click a path to see its last inspection and a link to its history.",
					"Hover over a path (or touch it on a phone) to see its name."];
			lines.push("The dashed line is the parish boundary; the map outside the parish is faded.");
			list(lines);
			heading("Path colours (latest grade)");
			list(grades.map(function (g) { return swatch(g[0], g[1] + " " + g[2]); }));
		}
		return box;
	}

	function text(tag, value, cls) {
		var el = document.createElement(tag);
		el.textContent = value;
		if (cls) {
			el.className = cls;
		}
		return el;
	}

	function pathPopup(p) {
		var box = document.createElement("div");
		var title = text("strong", p.label);
		box.appendChild(title);
		box.appendChild(document.createElement("br"));
		box.appendChild(text("span", p.date ? "Last inspected " + p.date + ", grade " + p.grade : "Not inspected"));
		if (p.url && !p.current) {
			box.appendChild(document.createElement("br"));
			var a = text("a", "Open path history");
			a.href = p.url;
			box.appendChild(a);
		}
		return box;
	}

	// A fault dot's popup: what it is, and a link down to its inspection's report on the same page.
	function faultPopup(f) {
		var box = document.createElement("div");
		box.appendChild(text("strong", f.label));
		box.appendChild(document.createElement("br"));
		var a = text("a", "Open this report");
		a.href = "#" + f.report;
		a.addEventListener("click", function (e) {
			var target = document.getElementById(f.report);
			if (!target) {
				return;
			}
			e.preventDefault();
			map.closePopup();
			// Older reports sit in a closed "Show older inspections" box: open it first.
			var details = target.closest("details");
			if (details) {
				details.open = true;
			}
			target.scrollIntoView({behavior: "smooth", block: "start"});
			target.focus({preventScroll: true});
			target.classList.remove("rafp-flash");
			void target.offsetWidth;
			target.classList.add("rafp-flash");
			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, "", "#" + f.report);
			}
		});
		box.appendChild(a);
		return box;
	}

	// Fade the map outside the given parish boundaries: a large box with the parishes cut out of it. The box
	// is about 150 km across rather than the whole world, because the OS grid (British National Grid) can't
	// project the world.
	function fadeOutside(boundaries, around) {
		var polygons = [];
		boundaries.forEach(function (geojson) {
			var g = geojson && geojson.type === "Feature" ? geojson.geometry : geojson;
			if (g && g.type === "Polygon") {
				polygons.push(g.coordinates);
			} else if (g && g.type === "MultiPolygon") {
				polygons = polygons.concat(g.coordinates);
			}
		});
		if (!polygons.length || !around.isValid()) {
			return;
		}
		var c = around.getCenter();
		var rings = [[[c.lat - 0.7, c.lng - 1.1], [c.lat - 0.7, c.lng + 1.1], [c.lat + 0.7, c.lng + 1.1], [c.lat + 0.7, c.lng - 1.1]]];
		polygons.forEach(function (poly) {
			rings.push(L.GeoJSON.coordsToLatLngs(poly[0]));
		});
		L.polygon(rings, {interactive: false, stroke: false, fillColor: "#ffffff", fillOpacity: 0.55}).addTo(map);
	}

	function parishPopup(p) {
		var box = document.createElement("div");
		box.appendChild(text("strong", p.name));
		var lines = [
			p.paths + (p.paths === 1 ? " path" : " paths"),
			p.recent + " inspected in the last 12 months" + (p.percent === null ? "" : " (" + p.percent + "%)"),
			p.never + " never inspected",
			p.last ? "Last inspection " + p.last : "No inspections"
		];
		lines.forEach(function (line) {
			box.appendChild(document.createElement("br"));
			box.appendChild(text("span", line));
		});
		box.appendChild(document.createElement("br"));
		var a = text("a", "Open " + p.name);
		a.href = p.url;
		box.appendChild(a);
		return box;
	}

	// Parishes page: every parish, shaded, labelled and clickable.
	function drawParishes(list, bounds) {
		// Names on the map when there's room; on a phone they would pile up, so show them on touch instead.
		var labels = window.innerWidth >= 768;
		var area = L.latLngBounds([]);
		var mine = L.latLngBounds([]);
		var layers = list.map(function (p) {
			// The signed-in inspector's own parishes get a heavy outline (and the map opens on them).
			var base = p.mine
				? {color: "#4A2160", weight: 5, opacity: 1, fillColor: p.colour, fillOpacity: 0.35}
				: {color: "#4A2160", weight: 2, opacity: 0.9, fillColor: p.colour, fillOpacity: 0.25};
			var layer = L.geoJSON(p.boundary, {style: base});
			layer.bindPopup(parishPopup(p));
			layer.on("mouseover", function () { layer.setStyle({weight: p.mine ? 6 : 3.5, fillOpacity: 0.5}); });
			layer.on("mouseout", function () { layer.setStyle(base); });
			area.extend(layer.getBounds());
			if (p.mine) {
				mine.extend(layer.getBounds());
			}
			return {layer: layer, name: p.name, mine: p.mine};
		});
		// Fade everything outside the club's parishes first, so the parishes sit on top of it.
		fadeOutside(list.map(function (p) { return p.boundary; }), area);
		layers.forEach(function (l) {
			l.layer.addTo(map);
			var cls = "rafp-parish-label" + (l.mine ? " rafp-parish-label-mine" : "");
			l.layer.bindTooltip(l.name, labels || l.mine ? {permanent: true, direction: "center", className: cls} : {sticky: true});
			if (l.mine) {
				l.layer.bringToFront();
			}
		});
		bounds.extend(area);
		return mine;
	}

	this.load = function () {
		var bounds = L.latLngBounds([]);
		var parish = L.latLngBounds([]);

		var mine = drawParishes(data.parishes || [], bounds);

		if (data.boundary) {
			var outline = L.geoJSON(data.boundary, {
				interactive: false,
				style: {color: "#4A2160", weight: 2.5, opacity: 0.9, fill: false, dashArray: "6 4"}
			});
			fadeOutside([data.boundary], outline.getBounds());
			outline.addTo(map);
			bounds.extend(outline.getBounds());
			parish.extend(outline.getBounds());
		}

		var current = L.latLngBounds([]);
		var paths = L.latLngBounds([]);

		(data.paths || []).forEach(function (p) {
			if (!p.route) {
				return;
			}
			if (p.current) {
				// White casing so the chosen path stands out from its neighbours.
				L.geoJSON(p.route, {interactive: false, style: {color: "#ffffff", weight: 10, opacity: 0.9}}).addTo(map);
			}
			var layer = L.geoJSON(p.route, {
				style: {color: p.colour, weight: p.current ? 7 : 5, opacity: p.current ? 1 : 0.9}
			});
			layer.bindPopup(pathPopup(p));
			layer.bindTooltip(p.label, {sticky: true});
			if (data.pick) {
				// A click on a path doesn't reach the map's own click handler, and faults are usually on the path.
				layer.on("click", picked);
			}
			layer.addTo(map);
			bounds.extend(layer.getBounds());
			paths.extend(layer.getBounds());
			if (p.current) {
				current.extend(layer.getBounds());
			}
		});

		(data.faults || []).forEach(function (f) {
			if (typeof f.lat !== "number" || typeof f.lng !== "number") {
				return;
			}
			var marker = L.circleMarker([f.lat, f.lng], {
				radius: 7, color: "#ffffff", weight: 2, fillColor: f.colour, fillOpacity: 1
			});
			marker.bindTooltip(f.label);
			if (f.report) {
				marker.bindPopup(faultPopup(f));
			}
			marker.addTo(map);
			current.extend(marker.getLatLng());
		});

		// Parish page: show the whole parish. Path page: this path and its faults. Parishes page: the
		// inspector's own parishes, or all of them.
		var target = current.isValid() ? current : (parish.isValid() ? parish : (mine.isValid() ? mine.pad(0.3) : bounds));
		function fit() {
			if (target.isValid()) {
				map.fitBounds(target, {padding: [8, 8], maxZoom: 16});
			}
		}
		fit();

		// The OS Explorer layer uses its own grid (British National Grid) with different zoom steps, so
		// fit again once it has replaced the starting layer, or the map ends up zoomed right out.
		map.on("baselayerchange", function () {
			if (autoSwitch) {
				autoSwitch = false;
				window.setTimeout(fit, 50);
			}
		});

		// The parishes page can start on its list, with the map hidden; size and fit it when it is shown.
		document.addEventListener("rafp-map-shown", function () {
			map.invalidateSize();
			fit();
		});

		if (data.pick) {
			pickable();
		}

		showOnMap();

		pickOsLayer();
	};

	// Path page: "Show on map" by a fault moves the map to it and marks it with a flashing red X (as the old
	// system did). On a phone, where the map is above the list, the page scrolls up to the map first.
	function showOnMap() {
		var x = null;
		var chosen = null;
		var icon = L.divIcon({
			className: "rafp-x",
			html: '<svg viewBox="0 0 48 48" width="48" height="48" aria-hidden="true"><path d="M9 9l30 30M39 9L9 39" stroke="#fff" stroke-width="13" stroke-linecap="round"/><path d="M9 9l30 30M39 9L9 39" stroke="#d50000" stroke-width="7" stroke-linecap="round"/></svg>',
			iconSize: [48, 48],
			iconAnchor: [24, 24]
		});
		document.addEventListener("click", function (e) {
			var button = e.target.closest ? e.target.closest(".rafp-show-on-map") : null;
			if (!button) {
				return;
			}
			var at = L.latLng(parseFloat(button.getAttribute("data-lat")), parseFloat(button.getAttribute("data-lng")));
			if (isNaN(at.lat) || isNaN(at.lng)) {
				return;
			}
			var box = document.getElementById(options.divId);
			var r = box.getBoundingClientRect();
			if (r.bottom < 0 || r.top > window.innerHeight) {
				box.scrollIntoView({behavior: "smooth", block: "center"});
			}
			if (x) {
				map.removeLayer(x);
			}
			if (chosen) {
				chosen.classList.remove("rafp-on-map");
			}
			chosen = button.closest(".rafp-fault");
			if (chosen) {
				chosen.classList.add("rafp-on-map");
			}
			x = L.marker(at, {icon: icon, interactive: false, keyboard: false, zIndexOffset: 1000}).addTo(map);
			x.bindTooltip(button.getAttribute("data-label") || "Fault", {permanent: false});
			// About 250 m around the fault: close enough to see the spot, with the path either side.
			map.fitBounds(at.toBounds(250), {maxZoom: 17});
		});
	}

	function picked(e) {
		document.dispatchEvent(new CustomEvent("rafp-map-pick", {detail: {lat: e.latlng.lat, lng: e.latlng.lng}}));
	}

	// Record-an-inspection form: a tap on the map tells the form where a fault is, and the form sends back the
	// numbered dots to draw (see footpaths-report.js).
	function pickable() {
		var dots = L.layerGroup().addTo(map);
		map.on("click", picked);
		map.on("popupopen", function () {
			// While placing a fault, a tap on a path places the fault rather than opening the path's details.
			if (document.querySelector(".rafp-picking")) {
				map.closePopup();
			}
		});
		document.addEventListener("rafp-markers", function (ev) {
			dots.clearLayers();
			(ev.detail.markers || []).forEach(function (m) {
				L.marker([m.lat, m.lng], {
					interactive: false,
					icon: L.divIcon({className: "rafp-pin", html: "<span>" + Number(m.n) + "</span>", iconSize: [28, 28], iconAnchor: [14, 14]})
				}).addTo(dots);
			});
			var f = ev.detail.focus;
			if (f && !map.getBounds().contains([f.lat, f.lng])) {
				map.panTo([f.lat, f.lng]);
			}
		});
	}

	// Set while we switch the layer ourselves, so only that switch re-fits the view (not the reader's own).
	var autoSwitch = false;

	// Open on OS Explorer/Landranger when the layer is there (it needs an OS key).
	function pickOsLayer() {
		var tries = 0;
		function find(name) {
			var labels = document.getElementById(options.divId).querySelectorAll(".leaflet-control-layers-base label");
			for (var i = 0; i < labels.length; i++) {
				if (labels[i].textContent.trim() === name) {
					return labels[i].querySelector("input");
				}
			}
			return null;
		}
		(function pick() {
			var input = find("OS Explorer/Landranger");
			if (input && !input.disabled) {
				if (!input.checked) {
					autoSwitch = true;
					input.click();
				}
				return;
			}
			if (++tries < 40) {
				window.setTimeout(pick, 250);
			}
		})();
	}
};
