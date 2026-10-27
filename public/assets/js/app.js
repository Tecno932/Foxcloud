'use strict';

const menuButton = document.querySelector('.menu-toggle');
const mainNav = document.querySelector('#main-nav');

if (menuButton && mainNav) {
    menuButton.addEventListener('click', () => {
        const isOpen = mainNav.classList.toggle('is-open');
        menuButton.setAttribute('aria-expanded', String(isOpen));
    });
}
