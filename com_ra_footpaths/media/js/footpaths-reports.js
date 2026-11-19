/**
 * Footpath reports: keep the "n chosen" counts up to date, and print the report once its photos have loaded
 * (photos load lazily, and a photo not yet loaded would print as a blank box).
 */
(() => {
	'use strict';

	document.querySelectorAll('[data-rafp-picked]').forEach((label) => {
		const boxes = label.closest('details').querySelectorAll('input[type=checkbox]');
		const update = () => {
			const n = [...boxes].filter((b) => b.checked).length;
			label.textContent = n ? n + ' chosen' : 'all';
		};
		boxes.forEach((b) => b.addEventListener('change', update));
	});

	const button = document.querySelector('[data-rafp-print]');

	if (!button) {
		return;
	}

	button.addEventListener('click', () => {
		const images = [...document.querySelectorAll('.rafp-report img')];
		images.forEach((img) => { img.loading = 'eager'; });
		const waits = images.filter((img) => !img.complete).map((img) => new Promise((done) => {
			img.addEventListener('load', done, { once: true });
			img.addEventListener('error', done, { once: true });
		}));

		button.disabled = true;
		const label = button.textContent;

		if (waits.length) {
			button.textContent = 'Loading ' + waits.length + ' photos…';
		}

		// Never wait more than 30 seconds for photos.
		Promise.race([Promise.all(waits), new Promise((done) => setTimeout(done, 30000))]).then(() => {
			button.disabled = false;
			button.textContent = label;
			window.print();
		});
	});
})();
