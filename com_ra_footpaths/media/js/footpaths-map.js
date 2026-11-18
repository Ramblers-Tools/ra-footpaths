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

	this.load = function () {
		var bounds = L.latLngBounds([]);

		if (data.boundary) {
			var outline = L.geoJSON(data.boundary, {
				interactive: false,
				style: {color: "#4A2160", weight: 2, opacity: 0.8, fill: true, fillOpacity: 0.04, dashArray: "6 4"}
			}).addTo(map);
			bounds.extend(outline.getBounds());
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

		// Fit to the paths (not the whole boundary), or to this path and its faults on a path page.
		var target = current.isValid() ? current : (paths.isValid() ? paths : bounds);
		// Fitting a whole parish leaves the OS map at Landranger scale (1:50,000), where paths are hard to
		// follow. So never open further out than Explorer scale (1:25,000): zoom 14 on the OS grid, about
		// the same as zoom 15 on the other maps. The reader can zoom out to see the rest.
		function fit() {
			if (!target.isValid()) {
				return;
			}
			map.fitBounds(target, {padding: [16, 16], maxZoom: 16});
			var closest = map.options.crs && map.options.crs.code === "EPSG:27700" ? 14 : 15;
			if (map.getZoom() < closest) {
				map.setView(target.getCenter(), closest);
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
