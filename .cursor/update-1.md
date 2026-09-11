Lets implement the following changes

1. we need to update the ui and tiptap editor ui and icons same as ckeditor 5 and also the content changes in editor are not visible as well lets fix it too
2. In the search dialog there are two dismiss buttons coming lets fix that issue 
3. Lets fix the ui for tagify in darkmode make the placeholder of input to white 
4. i am sharing you the script to display the sweet alert message for user add edit pages with backbutton etc also same we can use for settings save as well

    <script data-navigate-once>
        window.addEventListener('SweetMessage', ({
            detail: {
                type,
                title,
                message,
                url = false
            }
        }) => {
            switch (type) {
                case 'success':
                    if (url) {
                        Swal.fire({
                            title: title,
                            text: message,
                            icon: "success",
                            buttonsStyling: false,
                            showCancelButton: true,
                            cancelButtonText: 'Back To List',
                            confirmButtonText: "OK",
                            customClass: {
                                confirmButton: 'btn btn-primary me-3',
                                cancelButton: 'btn btn-label-secondary'
                            }
                        }).then((event) => {
                            if (!event.isConfirmed) {
                                Livewire.navigate(url)
                            }

                        });
                    } else {
                        Swal.fire({
                            title: title,
                            text: message,
                            icon: "success",
                            buttonsStyling: false,
                            confirmButtonText: "OK",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                    }
                    break;
                default:
                    Swal.fire({
                        title: title,
                        text: message,
                        icon: 'error'
                    });
                    break;
            }
        })
        window.addEventListener('OpenUrlNewTab', ({
            detail: {
                url
            }
        }) => {
            window.open(url, '_blank');
        })

        function dispatchEventCall(name = '') {
            const event = new CustomEvent(name);
            window.dispatchEvent(event);
        }
    </script>