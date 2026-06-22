<div class="modal fade cs-modal" id="enquiryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="cs-title">Enquiry Form</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php echo do_shortcode('[contact-form-7 id="8dc5fe9" title="Enquiry form"]'); ?>
            </div>
        </div>
    </div>
</div>
<style>
/* Contact Seller modal (cs- prefix) */
.cs-modal .modal-content {
    border: none;
    border-radius: 14px;
}

.cs-modal .modal-header {
    border-bottom: none;
    padding: 24px 28px 4px;
    align-items: flex-start;
}

.cs-title {
    font-weight: 800;
    font-size: 26px;
    color: #14213d;
    margin: 0;
}

.cs-modal .modal-body {
    padding: 16px 28px 28px;
}

.cs-label {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #8a96a3;
    margin-bottom: 8px;
    display: block;
}

.cs-control {
    width: 100%;
    border: 1px solid #dce3ec;
    border-radius: 8px;
    padding: 10px;
    font-size: 15px;
    color: #1b2430;
    background: #fafbfc;
    font-family: inherit;
    outline: none;
}

.cs-control::placeholder {
    color: #9aa5b1;
}

.cs-control:focus {
    border-color: #e6e6e6d5;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(21, 102, 216, 0);
}

textarea.cs-control {
    resize: vertical;
    min-height: 100px;
}

.cs-field {
    margin-bottom: 22px;
}

.cs-checkrow {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin: 6px 0 18px;
}

.cs-checkrow input {
    width: 22px;
    height: 22px;
    margin-top: 2px;
    accent-color: #1565d8;
    flex: none;
    cursor: pointer;
}

.cs-checkrow label {
    font-size: 16px;
    color: #1b2430;
    line-height: 1.35;
    cursor: pointer;
}

.cs-terms {
    font-size: 14px;
    color: #8a96a3;
    line-height: 1.5;
    margin-bottom: 22px;
}

.cs-terms a {
    color: #0d4fb8;
    text-decoration: none;
}


.cs-send {
    width: 100%;
    background: #0d4fb8;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 17px;
    font-weight: 800;
    font-size: 17px;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.cs-send:hover {
    background: #0a2647;
    color: #fff;
}
</style>
<script>
document.addEventListener('wpcf7mailsent', function () {
    setTimeout(function () {
        location.reload();
    }, 2000);
}, false);
</script>