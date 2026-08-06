/* 
* n3wmedia additions to Bootstrap5 Nav functionality to handle flyouts 
*/
// Sidebar collapse/expand behavior
(function() {
	const sidebar = document.getElementById('sidebar');
	const collapseBtn = document.getElementById('collapseBtn');
	const mobileMenuBtn = document.getElementById('mobileMenuBtn');
	const popouts = {
		organisations: document.getElementById('popout-organisations'),
		people: document.getElementById('popout-people')
	};

	function setCollapsed(collapsed)
	{
		
		if (collapsed) {
			sidebar.classList.add('collapsed');
			collapseBtn.innerHTML = '<i class="bi bi-chevron-right"></i>';
			collapseBtn.setAttribute('aria-pressed','true');
			//Reinstate Labels
			document.querySelectorAll('span.side-label').forEach(
			el=> {
				el.classList.add('d-none');
			});
		} else {
			sidebar.classList.remove('collapsed');
			collapseBtn.innerHTML = '<i class="bi bi-chevron-left"></i>';
			collapseBtn.setAttribute('aria-pressed','false');
			// Labels not needed when collapsed
			document.querySelectorAll('span.side-label').forEach(
			el=> {
				el.classList.remove('d-none');
			});
			hideAllPopouts();
		}
	}

	// Collapse Button
	collapseBtn.addEventListener('click', ()=>{
		console.log("inside addEventListener for collapse button");
		setCollapsed(!sidebar.classList.contains('collapsed'));
	});
	
	
	// Mobile toggle
	mobileMenuBtn.addEventListener('click', ()=>{
		sidebar.classList.toggle('open');
		// collapse relevant content in mobile context
		document.getElementById("collapseBtn").classList.add('is-hidden');
		document.querySelectorAll('span.side-label').forEach(
		el=> {
			el.classList.add('is-hidden');
		});

	});

	// show popout when hovering over nav-item with submenu while collapsed
	document.querySelectorAll('.nav-item.has-submenu').forEach(item=>{
		const key = item.dataset.menu;
		const pop = popouts[key];
		if (!pop)
			return;

		let hideTimer = null;

		function showPop(e)
		{
			// Close any other open popouts first
			hideAllPopouts();
			
			if (sidebar.classList.contains('collapsed)')) {
				
			}

			const rect = item.getBoundingClientRect();
			pop.style.top = (rect.top + rect.height / 2 - 12) + 'px';
			pop.classList.add('visible');
			pop.setAttribute('aria-hidden', 'false');
		}

		function hidePop()
		{
			pop.classList.remove('visible');
			pop.setAttribute('aria-hidden','true');
		}

		// HOVER Behavior: Only active if menu is collapsed
		item.addEventListener('mouseenter', ()=> {
			if (sidebar.classList.contains('collapsed)'))
				showPop();
		});

		item.addEventListener('focus', showPop);
		item.addEventListener('mouseleave', ()=>{ hideTimer = setTimeout(hidePop, 180); });
		item.addEventListener('blur', ()=>{ hideTimer = setTimeout(hidePop, 180); });

		pop.addEventListener('mouseenter', ()=>{
			if (hideTimer) {
				clearTimeout(hideTimer); hideTimer = null;
			} });
		pop.addEventListener('mouseleave', hidePop);

		// CLICK BEHAVIOR
		item.querySelector('.nav-link').addEventListener('click', (ev)=>{
			ev.preventDefault();
			ev.stopPropagation();
			showPop();
		});
	});

	function hideAllPopouts()
	{
		Object.values(popouts).forEach(p=>{ p.classList.remove('visible'); p.setAttribute('aria-hidden','true'); });
	}

	// click outside to close mobile sidebar or popouts
	document.addEventListener('click', (e)=>{
		if (window.innerWidth < 992) {
			if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
				sidebar.classList.remove('open');
			}
		}
		// hide popouts when clicking elsewhere
		if (!e.target.closest('.popout') && !e.target.closest('.has-submenu')) {
			hideAllPopouts();
		}
	});

	// keyboard accessibility: Esc closes popouts
	document.addEventListener('keydown', (e)=>{
		if (e.key==='Escape')
			hideAllPopouts(); });

	// collapse sidebar if viewport is small
	function onResize()
	{
		// deprecated to allow for default menu to be collapsed
		// If expanded then automatically collapse it when the screen is resized. 
		// (code retained for reference incase I want to change it back in the future)
		
		/*
		if (window.innerWidth < 1200)
			setCollapsed(true);
		else {
			setCollapsed(false);
			document.getElementById("collapseBtn").classList.remove('is-hidden');
			document.querySelectorAll('span.side-label').forEach(
			el=> {
				el.classList.remove('is-hidden');
			});
		}
		*/
		setCollapsed(true);
	}
	window.addEventListener('resize', onResize);
	onResize();
})();