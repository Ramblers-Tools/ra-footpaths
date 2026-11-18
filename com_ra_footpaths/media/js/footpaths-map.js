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

	// Fade the map outside the parish: a large box with the parish cut out of it. The box is about 150 km
	// across rather than the whole world, because the OS grid (British National Grid) can't project the world.
	function fadeOutside(geojson, around) {
		var g = geojson.type === "Feature" ? geojson.geometry : geojson;
		var polygons = g.type === "Polygon" ? [g.coordinates] : (g.type === "MultiPolygon" ? g.coordinates : []);
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

	this.load = function () {
		var bounds = L.latLngBounds([]);
		var parish = L.latLngBounds([]);

		if (data.boundary) {
			var outline = L.geoJSON(data.boundary, {
				interactive: false,
				style: {color: "#4A2160", weight: 2.5, opacity: 0.9, fill: false, dashArray: "6 4"}
			});
			fadeOutside(data.boundary, outline.getBounds());
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
