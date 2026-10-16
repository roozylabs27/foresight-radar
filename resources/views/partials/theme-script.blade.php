{{--
    Applies the saved theme before the first paint so dark users don't see a
    light flash. Light unless "Mode gelap" was switched on; mirrors
    resources/js/Components/ThemeProvider.jsx.
--}}
<script>
    (function () {
        var dark = false;
        try {
            dark = localStorage.getItem("foresight-theme") === "dark";
        } catch (e) {}
        var root = document.documentElement;
        root.classList.toggle("dark", dark);
        root.style.colorScheme = dark ? "dark" : "light";
    })();
</script>
