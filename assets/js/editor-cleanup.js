wp.domReady(() => {
	// Use MutationObserver to target the sidebar panel
	const observer = new MutationObserver(() => {
		document.querySelectorAll('.components-base-control').forEach(el => {
			const label = el.querySelector('label');
			if (label && label.textContent.includes('Resolution')) {
				el.style.display = 'none';
                console.log("Removed Resolution setting");
			}
		});
	});
	observer.observe(document.body, { childList: true, subtree: true });
});


wp.domReady(() => {
	const updateImageSrcs = () => {
		document.querySelectorAll('.wp-block-image img').forEach(img => {
			const original = img.getAttribute('src');
			if (original && /-\d+x\d+\.(jpe?g|png|webp|avif)$/i.test(original)) {
				const cleaned = original.replace(/-\d+x\d+(?=\.(jpe?g|png|webp|avif)$)/i, '');
				if (cleaned !== original) {
					console.log('[AVIF-EDITOR] Replacing preview src with:', cleaned);
					img.setAttribute('src', cleaned);
				}
			}
		});
	};

	// Initial run
	updateImageSrcs();

	// Re-run every time editor state changes
	wp.data.subscribe(() => {
		updateImageSrcs();
	});
});
