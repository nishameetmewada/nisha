(() => {
    'use strict';

    const source = document.getElementById('learning-catalog-data');
    if (!source) return;
    const catalog = JSON.parse(source.textContent);
    const courses = new Map(catalog.courses.map((course) => [course.id, course]));
    const courseLinks = [...document.querySelectorAll('#learning-course-tabs [data-course-id]')];
    const skillLinks = [...document.querySelectorAll('#learning-skill-filters [data-skill]')];
    const panels = [...document.querySelectorAll('.learning-course-panel')];
    const search = document.getElementById('learning-search');
    const form = document.getElementById('learning-search-form');
    const clear = document.getElementById('learning-clear-filters');
    const allowedSkills = new Set(skillLinks.map((link) => link.dataset.skill));
    const state = { ...catalog.initial };
    let inputTimer = null;

    const normalize = (text) => text.toLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
    const matchesSkill = (module, skill) => skill === 'all' || module.dataset.skill === skill || (skill === 'speaking-writing' && ['speaking', 'writing'].includes(module.dataset.skill));

    function url(course, skill = 'all', query = '') {
        const target = new URL(window.location.href);
        target.hash = '';
        for (const key of ['exam', 'course', 'skill', 'q']) target.searchParams.delete(key);
        target.searchParams.set('course', course);
        if (skill !== 'all') target.searchParams.set('skill', skill);
        if (query.trim()) target.searchParams.set('q', query.trim());
        return target;
    }

    function render(historyMode = null) {
        const course = courses.get(state.course);
        if (!course) return;
        search.value = state.q;
        document.getElementById('learning-course-input').value = state.course;
        document.getElementById('learning-skill-input').value = state.skill;
        document.getElementById('learning-library-title').textContent = course.name;
        document.getElementById('learning-course-description').textContent = course.description;

        let activeModules = [];
        panels.forEach((panel) => {
            const active = panel.dataset.course === state.course;
            panel.hidden = !active;
            panel.querySelector('.learning-course-sectionals').id = active ? 'learning-sectionals' : `learning-sectionals-${panel.dataset.course}`;
            if (active) activeModules = [...panel.querySelectorAll('.learning-module')];
        });

        courseLinks.forEach((link) => {
            const active = link.dataset.courseId === state.course;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
            link.href = url(link.dataset.courseId).href;
        });

        skillLinks.forEach((link) => {
            const skill = link.dataset.skill;
            const count = activeModules.filter((module) => matchesSkill(module, skill)).length;
            const active = skill === state.skill;
            link.hidden = skill !== 'all' && count === 0;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'true'); else link.removeAttribute('aria-current');
            link.querySelector('small').textContent = count;
            link.href = url(state.course, skill, state.q).href;
        });

        const terms = normalize(state.q).split(/\s+/u).filter(Boolean);
        let visible = 0;
        activeModules.forEach((module) => {
            const match = matchesSkill(module, state.skill) && terms.every((term) => module.dataset.search.includes(term));
            module.hidden = !match;
            if (match) visible++;
        });
        document.getElementById('learning-result-count').textContent = `${visible} ${visible === 1 ? 'question type' : 'question types'}`;
        document.getElementById('learning-empty').hidden = visible > 0;
        clear.href = url(state.course).href;
        if (historyMode) window.history[historyMode]({}, '', url(state.course, state.skill, state.q));
    }

    function isPlainClick(event) {
        return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
    }

    courseLinks.forEach((link) => link.addEventListener('click', (event) => {
        if (!isPlainClick(event)) return;
        event.preventDefault();
        window.clearTimeout(inputTimer);
        state.course = link.dataset.courseId;
        state.skill = 'all';
        state.q = '';
        render('pushState');
    }));

    skillLinks.forEach((link) => link.addEventListener('click', (event) => {
        if (!isPlainClick(event)) return;
        event.preventDefault();
        window.clearTimeout(inputTimer);
        state.skill = link.dataset.skill;
        state.q = search.value;
        render('pushState');
    }));

    search.addEventListener('input', () => {
        window.clearTimeout(inputTimer);
        inputTimer = window.setTimeout(() => {
            state.q = search.value;
            render('replaceState');
        }, 160);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(inputTimer);
        state.q = search.value;
        render('pushState');
    });

    clear.addEventListener('click', (event) => {
        if (!isPlainClick(event)) return;
        event.preventDefault();
        window.clearTimeout(inputTimer);
        state.skill = 'all';
        state.q = '';
        render('pushState');
        search.focus();
    });

    window.addEventListener('popstate', () => {
        window.clearTimeout(inputTimer);
        const query = new URLSearchParams(window.location.search);
        const requested = query.get('course');
        state.course = courses.has(requested) ? requested : (query.get('exam')?.toLowerCase() === 'pte' ? 'pte-academic' : 'ielts-academic');
        if (!courses.has(state.course)) state.course = catalog.courses[0].id;
        state.skill = allowedSkills.has(query.get('skill')) ? query.get('skill') : 'all';
        state.q = query.get('q') || '';
        render();
    });

    render();
})();
