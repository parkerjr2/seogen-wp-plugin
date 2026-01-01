(function () {
	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
			return;
		}
		fn();
	}

	ready(function () {
		if (typeof window.hyperLocalStickyCta !== 'object' || !window.hyperLocalStickyCta) {
			return;
		}

		var telUrl = window.hyperLocalStickyCta.telUrl;
		var label = window.hyperLocalStickyCta.label || 'Call Now';

		if (!telUrl) {
			return;
		}

		if (document.querySelector('.hyper-local-sticky-cta')) {
			return;
		}

		var bar = document.createElement('div');
		bar.className = 'hyper-local-sticky-cta';
		bar.innerHTML = '<a class="hyper-local-sticky-cta__button" href="' + telUrl + '">' + label + '</a>';

		document.body.appendChild(bar);
		document.body.classList.add('hyper-local-has-sticky-cta');
	});
})();
