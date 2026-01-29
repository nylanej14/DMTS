// File: /portal/assets/js/api-helper.js (Updated)
class OBRAPIHelper {
    constructor() {
        this.baseURL = window.location.origin + '/portal/api/';
    }
    
    async fetchWithTimeout(url, options = {}, timeout = 10000) {
        const controller = new AbortController();
        const id = setTimeout(() => controller.abort(), timeout);
        
        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });
            clearTimeout(id);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Fetch error:', error);
            return {
                success: false,
                error: error.message,
                data: []
            };
        }
    }
    
    async getDepartments() {
        try {
            const data = await this.fetchWithTimeout(this.baseURL + 'departments.php');
            return data.success ? data.data : [];
        } catch (error) {
            console.error('Error fetching departments:', error);
            return [];
        }
    }
    
    async getAccountCodes(search = '') {
        try {
            const url = search ? 
                `${this.baseURL}account-codes.php?search=${encodeURIComponent(search)}` :
                `${this.baseURL}account-codes.php`;
            
            const data = await this.fetchWithTimeout(url);
            return data.success ? data.data : [];
        } catch (error) {
            console.error('Error fetching account codes:', error);
            return [];
        }
    }
    
    // ... rest of the methods remain similar but with fetchWithTimeout ...
}

class OBRAPIHelper {
    // ... existing methods ...
    
    async getMunicipalities(provinceCode) {
        try {
            const response = await fetch(
                `${this.baseURL}address.php?action=municipalities&provinceCode=${provinceCode}`
            );
            const data = await response.json();
            return data.success ? data.data : [];
        } catch (error) {
            console.error('Error fetching municipalities:', error);
            return [];
        }
    }
    
    async getDepartmentSignatory(department) {
        try {
            const response = await fetch(
                `${this.baseURL}signatory.php?action=department&department=${encodeURIComponent(department)}`
            );
            const data = await response.json();
            return data.success ? data.data : null;
        } catch (error) {
            console.error('Error fetching department signatory:', error);
            return null;
        }
    }
    
    async saveOBR(obrData) {
        try {
            const response = await fetch(`${this.baseURL}save-obr.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(obrData)
            });
            return await response.json();
        } catch (error) {
            console.error('Error saving OBR:', error);
            return { success: false, error: error.message };
        }
    }
    
    // Helper method to populate dropdown
    populateDropdown(selectElement, data, valueField, textField, placeholder = 'Select...') {
        if (!selectElement) return;
        
        selectElement.innerHTML = '';
        
        // Add placeholder option
        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholder;
        placeholderOption.disabled = true;
        placeholderOption.selected = true;
        selectElement.appendChild(placeholderOption);
        
        // Add data options
        if (Array.isArray(data)) {
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item[valueField];
                option.textContent = item[textField];
                option.setAttribute('data-full', JSON.stringify(item));
                selectElement.appendChild(option);
            });
        }
    }
}