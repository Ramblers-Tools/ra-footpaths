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
	lmap.display();
	var map = lmap.map();
	data = data || {};

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
		var layers = list.map(function (p) {
			var base = {color: "#4A2160", weight: 2, opacity: 0.9, fillColor: p.colour, fillOpacity: 0.25};
			var layer = L.geoJSON(p.boundary, {style: base});
			layer.bindPopup(parishPopup(p));
			layer.on("mouseover", function () { layer.setStyle({weight: 3.5, fillOpacity: 0.5}); });
			layer.on("mouseout", function () { layer.setStyle(base); });
			area.extend(layer.getBounds());
			return {layer: layer, name: p.name};
		});
		// Fade everything outside the club's parishes first, so the parishes sit on top of it.
		fadeOutside(list.map(function (p) { return p.boundary; }), area);
		layers.forEach(function (l) {
			l.layer.addTo(map);
			l.layer.bindTooltip(l.name, labels ? {permanent: true, direction: "center", className: "rafp-parish-label"} : {sticky: true});
		});
		bounds.extend(area);
	}

	this.load = function () {
		var bounds = L.latLngBounds([]);
		var parish = L.latLngBounds([]);

		drawParishes(data.parishes || [], bounds);

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
			marker.addTo(map);
			current.extend(marker.getLatLng());
		});

		// Parish page: show the whole parish. Path page: this path and its faults.
		var target = current.isValid() ? current : (parish.isValid() ? parish : bounds);
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

		pickOsLayer();
	};

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
