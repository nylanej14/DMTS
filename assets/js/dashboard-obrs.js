// Simple OBR dashboard functions
$(document).ready(function() {
    console.log('OBR Dashboard loaded');
    
    // Quick action buttons
    $('.obr-action-btn').click(function() {
        let action = $(this).find('.obr-action-label').text().toLowerCase();
        console.log('OBR Action:', action);
    });
});