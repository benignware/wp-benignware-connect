document.addEventListener('DOMContentLoaded', function() {
    // Function to add the custom filter link
    function addCustomFilterLink() {
        const filterLinks = document.querySelector('.filter-links');

        if (filterLinks) {
            const newLink = document.createElement('li');
            newLink.innerHTML = '<a href="#" data-sort="benignware">Benignware</a>';
            filterLinks.appendChild(newLink);
        }
    }

    // Add the filter link on page load
    addCustomFilterLink();

    // Event delegation for filter links
    document.body.addEventListener('click', function(event) {
        const target = event.target;

        if (target.tagName === 'A' && target.dataset.sort === 'benignware') {
            event.preventDefault();

            // Remove 'current' class from all filter links
            document.querySelectorAll('.filter-links a').forEach(function(link) {
                link.classList.remove('current');
                link.removeAttribute('aria-current');
            });

            // Add 'current' class to the clicked link
            target.classList.add('current');
            target.setAttribute('aria-current', 'page');

            // Trigger WordPress's theme browser to display custom themes
            if (typeof wp !== 'undefined' && wp.updates && wp.updates.updateThemeTable) {
                wp.updates.updateThemeTable({browse: 'custom_themes'});
            }
        }
    });
});
