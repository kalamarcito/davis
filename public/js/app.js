'use strict'

// Calendar share modal
const shareModal = document.getElementById('shareModal')
if (shareModal) {
    shareModal.addEventListener('show.bs.modal', event => {
        // Button that triggered the modal
        const button = event.relatedTarget

        // Grab calendar shares url and add url
        let shareesUrl = button.getAttribute('data-sharees-href');
        let targetUrl = button.getAttribute('data-href');

        // When adding the sharee, catch the click to add the query parameter
        const addShareeButton = document.getElementById('shareModal-addSharee');
        addShareeButton.addEventListener("click", function(e) {
            const canWrite = document.getElementById('shareModal-canWrite').checked ? 'true' : 'false';
            const canCreate = document.getElementById('shareModal-canCreate').checked ? 'true' : 'false';
            const canDelete = document.getElementById('shareModal-canDelete').checked ? 'true' : 'false';
            const principalId = document.getElementById('shareModal-member').value;

            e.preventDefault()
            window.location = targetUrl + "?principalId=" + principalId + "&canWrite=" + canWrite + "&canCreate=" + canCreate + "&canDelete=" + canDelete
        });

        const noneElement = document.getElementById('shareModal-none')

        // Shares list
        const shares = document.getElementById('shareModal-shares')
        shares.innerHTML = ''

        // Get calendar shares
        fetch(shareesUrl)
            .then((response) => response.json())
            .then((data) => {

                // No sharee
                if (data.length === 0) {
                    noneElement.classList.remove("d-none");
                    return
                }
    
                noneElement.classList.add('d-none')

                // Share list item template
                const template = document.getElementById("shareModal-shareeTemplate");

                data.forEach(element => {
                    const clone = template.content.cloneNode(true);
                    clone.querySelector("span.name").textContent = element.displayName;
                    const badge = clone.querySelector("span.badge");
                    badge.textContent = element.accessText;
                    if (element.isWriteAccess) {
                        badge.classList.add('bg-success')
                        badge.classList.remove('bg-info')
                    }

                    // Pre-load the granular permissions the sharee currently has
                    const permWrite = clone.querySelector("input.perm-write");
                    const permCreate = clone.querySelector("input.perm-create");
                    const permDelete = clone.querySelector("input.perm-delete");
                    permWrite.checked = !!element.canWrite;
                    permCreate.checked = !!element.canCreate;
                    permDelete.checked = !!element.canDelete;

                    // Saving reuses the owner's share_add endpoint (upsert by principal)
                    const saveButton = clone.querySelector("a.save");
                    saveButton.addEventListener("click", function(e) {
                        e.preventDefault();
                        const cw = permWrite.checked ? 'true' : 'false';
                        const cc = permCreate.checked ? 'true' : 'false';
                        const cd = permDelete.checked ? 'true' : 'false';
                        window.location = targetUrl + "?principalId=" + element.principalId + "&canWrite=" + cw + "&canCreate=" + cc + "&canDelete=" + cd;
                    });

                    clone.querySelector("a.revoke").href = element.revokeUrl;

                    shares.appendChild(clone);
                });
                
            });
    })
}


// Delete modals (all kind of entities, so we use the rel, not the id)
const deleteModals = document.querySelectorAll('[rel="deleteModal"]');
deleteModals.forEach(element => {
    element.addEventListener('show.bs.modal', event => {
        // Button that triggered the modal
        const button = event.relatedTarget

        // Grab real target url for deletion
        let targetUrl = button.getAttribute('data-href');
        let modalFlavour = button.getAttribute('data-flavour');

        // Put it into the modal's OK button
        const deleteCTA = document.getElementById(`deleteModal-${modalFlavour}-cta`);
        console.log("setting href to " + targetUrl)
        deleteCTA.setAttribute('href', targetUrl);
    })
})



// Global account delegation modal
const addDelegateModal = document.getElementById('addDelegateModal')
if (addDelegateModal) {
    addDelegateModal.addEventListener('show.bs.modal', event => {
        // When adding the sharee, catch the click to add the query parameter
        const addDelegateButton = document.getElementById('addDelegateModal-cta');
        addDelegateButton.addEventListener("click", function(e) {
            const targetUrl = addDelegateButton.getAttribute('data-href');
            const writeAccess = document.getElementById('addDelegateModal-writeAccess').checked ? 'true' : 'false';
            const principalId = document.getElementById('addDelegateModal-member').value;

            e.preventDefault()
            window.location = targetUrl + "?principalId=" + principalId + "&write=" + writeAccess
        });

    })
}

// Color swatch: update it live (not working in IE ¯\_(ツ)_/¯ but it's just a nice to have)
const colorPicker = document.getElementById('calendar_instance_calendarColor');
if (colorPicker) {
    colorPicker.addEventListener('keyup', event => {
        document.body.style.setProperty('--calendar-color', event.target.value);
    })
    document.body.style.setProperty('--calendar-color', colorPicker.value);
}

// Bootstrap 5 popovers
const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]')
if (popoverTriggerList) {
    [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl))
}

// Bootstrap 5 toasts
const toastElList = document.querySelectorAll('.toast')
if (toastElList) {
    [...toastElList].map(toastEl => {
        const toast = new bootstrap.Toast(toastEl)
        toast.show()
    })
}
