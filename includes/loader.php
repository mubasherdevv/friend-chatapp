<?php
if (!defined('LOADER_INCLUDED')) {
    exit('Direct access to this file is not allowed.');
}
?>
<div id="loader" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="relative">
        <div class="w-16 h-16 border-4 border-blue-200 border-solid rounded-full"></div>
        <div class="absolute top-0 w-16 h-16 border-4 border-blue-500 border-t-transparent border-solid rounded-full animate-spin"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hide loader when page is fully loaded
    window.addEventListener('load', function() {
        const loader = document.getElementById('loader');
        if (loader) {
            loader.style.display = 'none';
        }
    });
});
</script>
