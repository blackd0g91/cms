{{--
    Apply the chosen theme before anything is drawn, so there is no flash.
    The site and the control panel share it (see resources/js/lib/theme.ts).
--}}
<script>
    try {
        const theme = localStorage.getItem('site.theme');
        if (theme === 'light' || theme === 'dark') document.documentElement.dataset.theme = theme;
    } catch {}
</script>
