document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('mouseover', () => {
            button.style.boxShadow = '0 0 15px lightblue';
        });
        button.addEventListener('mouseout', () => {
            button.style.boxShadow = 'none';
        });
    });
});

document.getElementById("login").onclick = function() {
    window.location.href = "/discord.php?action=login";
};