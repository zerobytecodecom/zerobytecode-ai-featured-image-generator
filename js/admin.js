document.addEventListener('DOMContentLoaded', function() {
    const generateBtn = document.getElementById('zerobytecode_generate_btn');
    if (generateBtn) {
        generateBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const postId = generateBtn.dataset.postid;

            // Update button to show loading state
            generateBtn.textContent = 'Generating...';
            generateBtn.disabled = true;

            // Call WordPress REST API to generate the image
            wp.apiRequest({
                path: zerobytecode.rest_url + postId,
                method: 'POST',
                data: {
                    _wpnonce: zerobytecode.nonce
                }
            }).done(function(response) {
                if (response.success) {
                    alert('Featured Image Generated Successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Something went wrong'));
                }
            }).fail(function(error) {
                alert('Request failed: ' + error.message);
            }).always(function() {
                // Reset button after operation
                generateBtn.textContent = 'Generate Image';
                generateBtn.disabled = false;
            });
        });
    }
});
