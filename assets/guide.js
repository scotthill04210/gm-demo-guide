/* Front-end tour, help, and glossary. Failures stay inside this file. */
(function () {
	'use strict';

	try {
		boot();
	} catch (err) {
		return;
	}

	function boot() {
		try {
			if (window.top !== window.self) {
				return;
			}
		} catch (err) {
			return;
		}

		var data = window.gmDemoGuide;
		if (!data || typeof data !== 'object') {
			return;
		}

		var strings = defaults({
			guide: 'Guide',
			close: 'Close',
			closeGuide: 'Close the guide',
			thisPage: 'This page',
			glossary: 'Glossary',
			tour: 'Tour',
			start: 'Start the tour',
			resume: 'Resume the tour',
			restart: 'Start over',
			end: 'End tour',
			back: 'Back',
			next: 'Next',
			continue: 'Continue',
			done: 'Done',
			step: 'Step %1$s of %2$s',
			noHelp: 'This page has no extra notes. The glossary explains the words used in the demo.',
			tryThis: 'Try this',
			search: 'Search the glossary',
			noTerms: 'No matching terms.',
			handoff: 'This part of the tour is on another page. Open that page and the tour will continue there.',
			tourIntro: 'A short tour of the demo. You can leave it and come back.',
			noTour: 'No tour is available.'
		}, data.strings);

		var tour = cleanTour(data.tour);
		var steps = tour ? tour.steps : [];
		var help = cleanHelp(data.help);
		var glossary = cleanGlossary(data.glossary);
		if (!steps.length && !help && !glossary.length) {
			return;
		}

		var storageKey = 'gm-demo-guide';
		var state = loadState();
		var suppressed = false;
		var currentTarget = null;
		var frame = 0;
		var root;
		var launcher;
		var scrim;
		var drawer;
		var tooltip;
		var spotlight;
		var kicker;
		var titleNode;
		var bodyNode;
			var backBtn;
			var nextBtn;
			var endBtn;
			var nextHandler = function () {};
		var tabButtons = [];
		var panels = [];
		var tourMount;

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', guard(build));
		} else {
			build();
		}

		window.addEventListener('pageshow', guard(function (event) {
			if (!event.persisted) {
				return;
			}
			state = loadState();
			suppressed = false;
			paintLauncher();
			reconcile(false);
		}));

		function build() {
			if (!document.body || document.getElementById('gm-guide-root')) {
				return;
			}

			root = el('div');
			root.id = 'gm-guide-root';

			launcher = el('button', 'gm-guide-launcher', strings.guide);
			launcher.type = 'button';
			launcher.setAttribute('aria-expanded', 'false');
			launcher.setAttribute('aria-controls', 'gm-guide-drawer');
			launcher.addEventListener('click', guard(function () {
				if (drawer.hidden) {
					openDrawer();
				} else {
					closeDrawer();
				}
			}));

			scrim = el('button', 'gm-guide-scrim');
			scrim.type = 'button';
			scrim.hidden = true;
			scrim.setAttribute('aria-label', strings.closeGuide);
			scrim.addEventListener('click', guard(function () {
				closeDrawer();
			}));

			drawer = buildDrawer();
			spotlight = el('div', 'gm-guide-spotlight');
			spotlight.hidden = true;
			tooltip = buildTooltip();

			root.appendChild(launcher);
			root.appendChild(scrim);
			root.appendChild(drawer);
			root.appendChild(spotlight);
			root.appendChild(tooltip);
			document.body.appendChild(root);

			root.addEventListener('keydown', guard(onKeydown));
			window.addEventListener('resize', guard(schedulePlace), { passive: true });
			window.addEventListener('scroll', guard(schedulePlace), { passive: true, capture: true });

			paintLauncher();
			renderHelp();
			renderGlossary('');
			reconcile(false);
		}

		function buildDrawer() {
			var panel = el('aside', 'gm-guide-drawer');
			panel.id = 'gm-guide-drawer';
			panel.hidden = true;
			panel.setAttribute('role', 'dialog');
			panel.setAttribute('aria-modal', 'false');
			panel.setAttribute('aria-labelledby', 'gm-guide-drawer-title');

			var head = el('div', 'gm-guide-drawer-head');
			var heading = el('h2', null, strings.guide);
			heading.id = 'gm-guide-drawer-title';
			var closeBtn = el('button', 'gm-guide-btn gm-guide-btn-quiet', strings.close);
			closeBtn.type = 'button';
			closeBtn.addEventListener('click', guard(function () {
				closeDrawer();
			}));
			head.appendChild(heading);
			head.appendChild(closeBtn);

			var tabs = el('div', 'gm-guide-tabs');
			tabs.setAttribute('role', 'tablist');
			tabButtons = [
				tabButton('page', strings.thisPage),
				tabButton('glossary', strings.glossary),
				tabButton('tour', strings.tour)
			];
			tabButtons.forEach(function (button) {
				tabs.appendChild(button);
			});

			var scroll = el('div', 'gm-guide-scroll');
			panels = [
				el('div', 'gm-guide-panel'),
				el('div', 'gm-guide-panel'),
				el('div', 'gm-guide-panel')
			];
			panels[0].setAttribute('data-panel', 'page');
			panels[1].setAttribute('data-panel', 'glossary');
			panels[2].setAttribute('data-panel', 'tour');
			tourMount = panels[2];
			panels.forEach(function (section) {
				scroll.appendChild(section);
			});

			panel.appendChild(head);
			panel.appendChild(tabs);
			panel.appendChild(scroll);
			return panel;
		}

		function tabButton(name, label) {
			var button = el('button', 'gm-guide-tab', label);
			button.type = 'button';
			button.setAttribute('role', 'tab');
			button.setAttribute('data-tab', name);
			button.setAttribute('aria-selected', 'false');
			button.addEventListener('click', guard(function () {
				showTab(name);
			}));
			return button;
		}

		function buildTooltip() {
			var card = el('div', 'gm-guide-tooltip');
			card.id = 'gm-guide-tooltip';
			card.hidden = true;
			card.setAttribute('role', 'dialog');
			card.setAttribute('aria-modal', 'false');
			card.setAttribute('aria-labelledby', 'gm-guide-title');
			card.setAttribute('aria-describedby', 'gm-guide-body');

			var head = el('div', 'gm-guide-tooltip-head');
			kicker = el('p', 'gm-guide-kicker');
			kicker.setAttribute('aria-live', 'polite');
			endBtn = el('button', 'gm-guide-btn gm-guide-btn-quiet', strings.end);
			endBtn.type = 'button';
			endBtn.addEventListener('click', guard(endTour));
			head.appendChild(kicker);
			head.appendChild(endBtn);

			titleNode = el('h2');
			titleNode.id = 'gm-guide-title';
			bodyNode = el('div', 'gm-guide-copy');
			bodyNode.id = 'gm-guide-body';

			var actions = el('div', 'gm-guide-actions');
			backBtn = el('button', 'gm-guide-btn', strings.back);
			backBtn.type = 'button';
			nextBtn = el('button', 'gm-guide-btn gm-guide-btn-primary', strings.next);
			nextBtn.type = 'button';
			backBtn.addEventListener('click', guard(goBack));
			nextBtn.addEventListener('click', guard(function () {
				nextHandler();
			}));
			actions.appendChild(backBtn);
			actions.appendChild(nextBtn);

			card.appendChild(head);
			card.appendChild(titleNode);
			card.appendChild(bodyNode);
			card.appendChild(actions);
			return card;
		}

		function renderHelp() {
			var wrap = el('div', 'gm-guide-copy');
			var heading = el('h2');
			var copy = el('div');
			wrap.appendChild(heading);
			wrap.appendChild(copy);
			if (!help) {
				heading.hidden = true;
				fillText(copy, strings.noHelp);
			} else {
				heading.textContent = help.title;
				fillText(copy, help.body);
				if (help.tips.length) {
					wrap.appendChild(el('h3', null, strings.tryThis));
					var list = el('ul', 'gm-guide-tips');
					help.tips.forEach(function (tip) {
						list.appendChild(el('li', null, tip));
					});
					wrap.appendChild(list);
				}
			}
			panels[0].appendChild(wrap);
		}

		function renderGlossary(query) {
			var panel = panels[1];
			while (panel.firstChild) {
				panel.removeChild(panel.firstChild);
			}
			var input = el('input', 'gm-guide-search');
			input.type = 'search';
			input.value = query || '';
			input.setAttribute('aria-label', strings.search);
			input.placeholder = strings.search;
			input.addEventListener('input', guard(function () {
				renderGlossary(input.value);
				var field = panels[1].querySelector('input');
				if (field) {
					field.focus();
					var end = field.value.length;
					try {
						field.setSelectionRange(end, end);
					} catch (err) {
						return;
					}
				}
			}));
			panel.appendChild(input);

			var q = String(query || '').trim().toLowerCase();
			var shown = 0;
			glossary.forEach(function (item) {
				var hay = (item.term + ' ' + item.definition).toLowerCase();
				if (q && hay.indexOf(q) === -1) {
					return;
				}
				shown += 1;
				var block = el('div', 'gm-guide-term');
				block.appendChild(el('h3', null, item.term));
				block.appendChild(el('p', null, item.definition));
				panel.appendChild(block);
			});
			var empty = el('p', 'gm-guide-note', strings.noTerms);
			empty.hidden = shown !== 0;
			panel.appendChild(empty);
		}

		function renderTourPanel() {
			while (tourMount.firstChild) {
				tourMount.removeChild(tourMount.firstChild);
			}
			if (!tour) {
				tourMount.appendChild(el('p', null, strings.noTour));
				return;
			}
			tourMount.appendChild(el('h2', null, tour.title));
			tourMount.appendChild(el('p', null, strings.tourIntro));
			var actions = el('div', 'gm-guide-actions');
			if (state.active) {
				actions.appendChild(actionButton(strings.resume, 'gm-guide-btn gm-guide-btn-primary', function () {
					resumeTour();
				}));
				actions.appendChild(actionButton(strings.restart, 'gm-guide-btn', function () {
					startTour();
				}));
			} else {
				actions.appendChild(actionButton(strings.start, 'gm-guide-btn gm-guide-btn-primary', function () {
					startTour();
				}));
			}
			tourMount.appendChild(actions);

			var list = el('ol', 'gm-guide-steps');
			steps.forEach(function (step, index) {
				var item = el('li');
				if (state.active && step.id === state.stepId) {
					item.setAttribute('aria-current', 'step');
				}
				var label = String(index + 1) + '. ' + step.title;
				if (step.matches || step.href) {
					var button = el('button', 'gm-guide-step', label);
					button.type = 'button';
					button.addEventListener('click', guard(function () {
						jump(step);
					}));
					item.appendChild(button);
				} else {
					item.appendChild(el('span', 'gm-guide-step', label));
				}
				list.appendChild(item);
			});
			tourMount.appendChild(list);
		}

		function actionButton(label, className, onClick) {
			var button = el('button', className, label);
			button.type = 'button';
			button.addEventListener('click', guard(onClick));
			return button;
		}

		function showTab(name) {
			tabButtons.forEach(function (button) {
				var on = button.getAttribute('data-tab') === name;
				button.setAttribute('aria-selected', on ? 'true' : 'false');
			});
			panels.forEach(function (panel) {
				panel.hidden = panel.getAttribute('data-panel') !== name;
			});
			if (name === 'tour') {
				renderTourPanel();
			}
		}

		function openDrawer() {
			drawer.hidden = false;
			scrim.hidden = false;
			launcher.setAttribute('aria-expanded', 'true');
			tooltip.hidden = true;
			hideSpotlight();
			showTab('page');
			try {
				drawer.querySelector('button').focus();
			} catch (err) {
				return;
			}
		}

		function closeDrawer(silent) {
			if (!drawer || drawer.hidden) {
				return;
			}
			drawer.hidden = true;
			scrim.hidden = true;
			launcher.setAttribute('aria-expanded', 'false');
			if (silent) {
				return;
			}
			if (state.active && !suppressed) {
				reconcile(false);
			}
			if (tooltip.hidden) {
				try {
					launcher.focus();
				} catch (err) {
					return;
				}
			}
		}

		function startTour() {
			if (!steps.length) {
				return;
			}
			suppressed = false;
			closeDrawer(true);
			var first = steps[0];
			if (first.matches) {
				showStep(first, false);
				return;
			}
			state.active = true;
			state.stepId = first.id;
			state.handoff = false;
			state.tourId = tour.id;
			saveState();
			if (first.href && safeGo(first.href)) {
				return;
			}
			showStep(first, true);
		}

		function resumeTour() {
			suppressed = false;
			closeDrawer(true);
			reconcile(true);
		}

		function endTour() {
			state.active = false;
			state.handoff = false;
			saveState();
			hideTooltip();
			try {
				launcher.focus();
			} catch (err) {
				return;
			}
		}

		function jump(step) {
			suppressed = false;
			closeDrawer(true);
			if (step.matches) {
				showStep(step, false);
				return;
			}
			state.active = true;
			state.stepId = step.id;
			state.handoff = true;
			state.tourId = tour.id;
			saveState();
			if (step.href && safeGo(step.href)) {
				return;
			}
			showStep(step, true);
		}

		function reconcile(force) {
			if (!tour || !state.active || state.tourId !== tour.id) {
				hideTooltip();
				return;
			}
			var step = findStep(state.stepId);
			if (!step) {
				hideTooltip();
				return;
			}
			if (suppressed && !force) {
				hideTooltip();
				return;
			}
			suppressed = false;
			if (step.matches) {
				showStep(step, false);
				return;
			}
			if (state.handoff) {
				showStep(step, true);
				return;
			}
			var index = indexOf(step);
			var next = steps[index + 1];
			if (next && next.matches) {
				showStep(next, false);
				return;
			}
			if (force) {
				showStep(step, true);
				return;
			}
			hideTooltip();
		}

		function showStep(step, handoff) {
			if (!step || !tooltip) {
				return;
			}
			state.active = true;
			state.stepId = step.id;
			state.handoff = !!handoff;
			state.tourId = tour.id;
			saveState();

			var index = indexOf(step);
			kicker.textContent = stepLabel(index + 1, steps.length);
			titleNode.textContent = step.title;
			fillText(bodyNode, handoff ? strings.handoff : step.body);
			backBtn.disabled = index <= 0;

			if (handoff) {
				if (step.href) {
					nextBtn.hidden = false;
					nextBtn.textContent = strings.continue;
					nextHandler = function () {
						safeGo(step.href);
					};
				} else {
					nextBtn.hidden = true;
					nextHandler = function () {};
				}
			} else {
				nextBtn.hidden = false;
				nextBtn.textContent = nextLabel(index);
				nextHandler = goNext;
			}

			tooltip.hidden = false;
			currentTarget = null;
			if (!handoff) {
				currentTarget = findTarget(step.target);
				if (currentTarget) {
					try {
						currentTarget.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' });
					} catch (err) {
						currentTarget = null;
					}
				}
			}
			place();
			try {
				(nextBtn.hidden ? endBtn : nextBtn).focus();
			} catch (err) {
				return;
			}
		}

		function goNext() {
			var index = indexOfId(state.stepId);
			if (index < 0) {
				return;
			}
			if (index >= steps.length - 1) {
				endTour();
				return;
			}
			var next = steps[index + 1];
			if (next.matches) {
				showStep(next, false);
				return;
			}
			state.stepId = next.id;
			state.handoff = true;
			state.active = true;
			saveState();
			if (next.href && safeGo(next.href)) {
				return;
			}
			showStep(next, true);
		}

		function goBack() {
			var index = indexOfId(state.stepId);
			if (index <= 0) {
				return;
			}
			var prev = steps[index - 1];
			if (prev.matches) {
				showStep(prev, false);
				return;
			}
			state.stepId = prev.id;
			state.handoff = false;
			state.active = true;
			saveState();
			if (prev.href && safeGo(prev.href)) {
				return;
			}
			showStep(prev, true);
		}

		function hideTooltip() {
			if (!tooltip) {
				return;
			}
			tooltip.hidden = true;
			hideSpotlight();
			currentTarget = null;
		}

		function hideSpotlight() {
			if (spotlight) {
				spotlight.hidden = true;
			}
		}

		function schedulePlace() {
			if (!tooltip || tooltip.hidden) {
				return;
			}
			if (frame) {
				return;
			}
			frame = window.requestAnimationFrame(function () {
				frame = 0;
				try {
					place();
				} catch (err) {
					frame = 0;
				}
			});
		}

		function place() {
			if (!tooltip || tooltip.hidden) {
				return;
			}
			var target = currentTarget && usable(currentTarget) ? currentTarget : null;
			if (!target) {
				hideSpotlight();
				centerTooltip();
				return;
			}
			showSpotlight(target);
			if (window.innerWidth < 640 || !anchorTooltip(target)) {
				pinTooltip(target);
			}
		}

		function resetBox() {
			tooltip.style.top = 'auto';
			tooltip.style.left = 'auto';
			tooltip.style.right = 'auto';
			tooltip.style.bottom = 'auto';
			tooltip.style.width = '';
			tooltip.style.maxWidth = '';
			tooltip.style.transform = 'none';
		}

		function centerTooltip() {
			resetBox();
			tooltip.style.top = '50%';
			tooltip.style.left = '50%';
			tooltip.style.transform = 'translate(-50%, -50%)';
		}

		function anchorTooltip(target) {
			resetBox();
			var rect = target.getBoundingClientRect();
			var gap = 12;
			var tw = tooltip.offsetWidth;
			var th = tooltip.offsetHeight;
			if (!tw || !th) {
				return false;
			}
			var top = rect.bottom + gap;
			var left = rect.left;
			if (left + tw > window.innerWidth - 16) {
				left = window.innerWidth - tw - 16;
			}
			if (left < 16) {
				left = 16;
			}
			if (top + th > window.innerHeight - 16) {
				var above = rect.top - gap - th;
				if (above < 16) {
					return false;
				}
				top = above;
			}
			if (top < 16) {
				return false;
			}
			tooltip.style.top = top + 'px';
			tooltip.style.left = left + 'px';
			return true;
		}

		function pinTooltip(target) {
			resetBox();
			tooltip.style.left = '16px';
			tooltip.style.right = '16px';
			tooltip.style.width = 'auto';
			tooltip.style.maxWidth = 'none';
			var rect = target.getBoundingClientRect();
			var height = tooltip.offsetHeight || 180;
			var sheetTop = window.innerHeight - height - 72;
			if (rect.bottom > sheetTop && rect.top > height + 32) {
				tooltip.style.top = '16px';
			} else {
				tooltip.style.bottom = '72px';
			}
		}

		function showSpotlight(target) {
			var rect = target.getBoundingClientRect();
			var pad = 6;
			spotlight.hidden = false;
			spotlight.style.top = (rect.top - pad) + 'px';
			spotlight.style.left = (rect.left - pad) + 'px';
			spotlight.style.width = (rect.width + pad * 2) + 'px';
			spotlight.style.height = (rect.height + pad * 2) + 'px';
		}

		function onKeydown(event) {
			if (event.key === 'Escape') {
				if (!root.contains(event.target)) {
					return;
				}
				if (!drawer.hidden) {
					event.preventDefault();
					closeDrawer();
					return;
				}
				if (!tooltip.hidden) {
					event.preventDefault();
					suppressed = true;
					hideTooltip();
					try {
						launcher.focus();
					} catch (err) {
						return;
					}
				}
				return;
			}
			if (event.key !== 'Tab') {
				return;
			}
			var container = !drawer.hidden ? drawer : (!tooltip.hidden ? tooltip : null);
			if (!container) {
				return;
			}
			var items = focusables(container);
			if (!items.length) {
				return;
			}
			var first = items[0];
			var last = items[items.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		}

		function focusables(container) {
			var nodes = container.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])');
			var out = [];
			var i;
			for (i = 0; i < nodes.length; i++) {
				if (nodes[i].disabled || nodes[i].hidden || nodes[i].closest('[hidden]')) {
					continue;
				}
				out.push(nodes[i]);
			}
			return out;
		}

		function findTarget(selector) {
			if (!selector || typeof selector !== 'string') {
				return null;
			}
			var list;
			try {
				list = document.querySelectorAll(selector);
			} catch (err) {
				return null;
			}
			var max = Math.min(list.length, 25);
			var i;
			for (i = 0; i < max; i++) {
				if (usable(list[i])) {
					return list[i];
				}
			}
			return null;
		}

		function usable(node) {
			try {
				if (!node || !node.getBoundingClientRect || !node.isConnected) {
					return false;
				}
				var rect = node.getBoundingClientRect();
				if (rect.width < 2 || rect.height < 2) {
					return false;
				}
				var style = window.getComputedStyle(node);
				if (!style || style.display === 'none' || style.visibility === 'hidden') {
					return false;
				}
				return true;
			} catch (err) {
				return false;
			}
		}

		function safeGo(href) {
			try {
				var url = new URL(href, window.location.href);
				if (url.origin !== window.location.origin) {
					return false;
				}
				var path = url.pathname.toLowerCase();
				if (path.indexOf('/wp-admin/') !== -1 || path.indexOf('post-new.php') !== -1) {
					return false;
				}
				window.location.assign(url.href);
				return true;
			} catch (err) {
				return false;
			}
		}

		function paintLauncher() {
			if (!launcher) {
				return;
			}
			if (state.active) {
				launcher.setAttribute('data-active', 'true');
			} else {
				launcher.removeAttribute('data-active');
			}
		}

		function loadState() {
			var empty = {
				v: 1,
				tourId: tour ? tour.id : '',
				stepId: steps.length ? steps[0].id : '',
				active: false,
				handoff: false
			};
			try {
				var raw = window.localStorage.getItem(storageKey);
				if (!raw) {
					return empty;
				}
				var parsed = JSON.parse(raw);
				if (!parsed || parsed.v !== 1 || parsed.tourId !== empty.tourId || !findStep(parsed.stepId)) {
					return empty;
				}
				empty.stepId = parsed.stepId;
				empty.active = !!parsed.active;
				empty.handoff = !!parsed.handoff;
				return empty;
			} catch (err) {
				return empty;
			}
		}

		function saveState() {
			try {
				window.localStorage.setItem(storageKey, JSON.stringify({
					v: 1,
					tourId: state.tourId,
					stepId: state.stepId,
					active: !!state.active,
					handoff: !!state.handoff
				}));
			} catch (err) {
				/* Storage can be blocked. The tour still works for this page. */
			}
			paintLauncher();
		}

		function findStep(id) {
			var i;
			for (i = 0; i < steps.length; i++) {
				if (steps[i].id === id) {
					return steps[i];
				}
			}
			return null;
		}

		function indexOf(step) {
			return indexOfId(step ? step.id : '');
		}

		function indexOfId(id) {
			var i;
			for (i = 0; i < steps.length; i++) {
				if (steps[i].id === id) {
					return i;
				}
			}
			return -1;
		}

		function nextLabel(index) {
			if (index >= steps.length - 1) {
				return strings.done;
			}
			var next = steps[index + 1];
			if (next && !next.matches && next.href) {
				return strings.continue;
			}
			return strings.next;
		}

		function stepLabel(current, total) {
			return String(strings.step || 'Step %1$s of %2$s')
				.replace('%1$s', String(current))
				.replace('%2$s', String(total));
		}

		function fillText(container, text) {
			while (container.firstChild) {
				container.removeChild(container.firstChild);
			}
			var parts = String(text || '').split('\n');
			var count = 0;
			var i;
			for (i = 0; i < parts.length; i++) {
				var line = parts[i].trim();
				if (!line) {
					continue;
				}
				var p = document.createElement('p');
				p.textContent = line;
				container.appendChild(p);
				count += 1;
			}
			if (!count) {
				container.appendChild(document.createElement('p'));
			}
		}

		function el(tag, className, text) {
			var node = document.createElement(tag);
			if (className) {
				node.className = className;
			}
			if (typeof text === 'string') {
				node.textContent = text;
			}
			return node;
		}

		function guard(fn) {
			return function () {
				try {
					return fn.apply(this, arguments);
				} catch (err) {
					return undefined;
				}
			};
		}
	}

	function defaults(base, extra) {
		var out = {};
		var key;
		for (key in base) {
			if (Object.prototype.hasOwnProperty.call(base, key)) {
				out[key] = base[key];
			}
		}
		if (extra && typeof extra === 'object') {
			for (key in extra) {
				if (Object.prototype.hasOwnProperty.call(extra, key) && typeof extra[key] === 'string' && extra[key] !== '') {
					out[key] = extra[key];
				}
			}
		}
		return out;
	}

	function cleanTour(raw) {
		if (!raw || typeof raw !== 'object' || !Array.isArray(raw.steps)) {
			return null;
		}
		var cleaned = [];
		var i;
		for (i = 0; i < raw.steps.length; i++) {
			var step = cleanStep(raw.steps[i]);
			if (step) {
				cleaned.push(step);
			}
		}
		if (!cleaned.length) {
			return null;
		}
		return {
			id: typeof raw.id === 'string' && raw.id ? raw.id : 'tour',
			title: typeof raw.title === 'string' ? raw.title : '',
			steps: cleaned
		};
	}

	function cleanStep(step) {
		if (!step || typeof step.id !== 'string' || typeof step.title !== 'string' || !step.id || !step.title) {
			return null;
		}
		return {
			id: step.id,
			title: step.title,
			body: typeof step.body === 'string' ? step.body : '',
			target: typeof step.target === 'string' && step.target ? step.target : null,
			matches: !!step.matches,
			href: typeof step.href === 'string' && step.href ? step.href : null
		};
	}

	function cleanHelp(raw) {
		if (!raw || typeof raw !== 'object' || typeof raw.title !== 'string' || !raw.title) {
			return null;
		}
		var tips = [];
		if (Array.isArray(raw.tips)) {
			var i;
			for (i = 0; i < raw.tips.length; i++) {
				if (typeof raw.tips[i] === 'string' && raw.tips[i]) {
					tips.push(raw.tips[i]);
				}
			}
		}
		return {
			title: raw.title,
			body: typeof raw.body === 'string' ? raw.body : '',
			tips: tips
		};
	}

	function cleanGlossary(raw) {
		var out = [];
		if (!Array.isArray(raw)) {
			return out;
		}
		var i;
		for (i = 0; i < raw.length; i++) {
			var item = raw[i];
			if (!item || typeof item.term !== 'string' || typeof item.definition !== 'string') {
				continue;
			}
			if (!item.term || !item.definition) {
				continue;
			}
			out.push({
				id: typeof item.id === 'string' ? item.id : '',
				term: item.term,
				definition: item.definition
			});
		}
		return out;
	}
})();
