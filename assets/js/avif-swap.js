document.addEventListener('DOMContentLoaded', function () {
	console.log('[AVIF-SWAP] Starting image replacement');

	document.querySelectorAll('img[data-avif]').forEach((img) => {
		const avifUrl = img.dataset.avif;
		const fallbackSrc = img.dataset.src || '';
		const fallbackSrcset = img.dataset.srcset || '';
		const fallbackSizes = img.dataset.sizes || '';
		const fallbackPriority = img.dataset.fetchpriority || '';

		if (!avifUrl) return;

		const clone = img.cloneNode(true);

		// Clear all preload triggers before setting AVIF
		clone.removeAttribute('src');
		clone.removeAttribute('srcset');
		clone.removeAttribute('sizes');
		clone.removeAttribute('fetchpriority');

		clone.src = avifUrl;

		// Graceful fallback if AVIF fails
		clone.onerror = function () {
			console.warn('[AVIF-SWAP] AVIF failed, falling back:', fallbackSrc);
			if (fallbackSrc) clone.src = fallbackSrc;
			if (fallbackSrcset) clone.setAttribute('srcset', fallbackSrcset);
			if (fallbackSizes) clone.setAttribute('sizes', fallbackSizes);
			if (fallbackPriority) clone.setAttribute('fetchpriority', fallbackPriority);
		};

		img.replaceWith(clone);
	});
});
