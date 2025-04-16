document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('.official_cdek-show_uin').forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.preventDefault();

            const element = event.target;
            const container = element.nextElementSibling;

            if (container && container.classList.contains("hidden")) {
                element.classList.add("hidden");
                container.classList.remove("hidden");
            }
        });
    });

    document.querySelectorAll('.official_cdek-save_uin').forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.preventDefault();
            const itemId = event.target.closest('.uin-input-container').dataset.id;
            const input = document.querySelector(`#official_cdek_jewel_uin_${itemId}`);

            if (window.cdek.saver !== undefined) {
                fetch(window.cdek.saver, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: new URLSearchParams({
                                                  item_id: itemId,
                                                  jewel_uin: input ? input.value : ""
                                              })
                })
                    .then(response => response.json())
                    .then(data => console.debug("[CDEK-MAP] UIN saved:", data))
                    .catch(error => console.error("Error:", error));
            }
        });
    });
});
