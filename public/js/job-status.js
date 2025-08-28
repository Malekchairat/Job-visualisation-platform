/**
 * Job Status Dashboard - Enhanced Interactivity
 */

class JobStatusDashboard {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.startAutoRefresh();
    }

    init() {
        this.addStatusAnimations();
        this.setupTooltips();
        this.enhanceTableInteractivity();
    }

    setupEventListeners() {
        // Enhanced search functionality
        const searchInput = document.getElementById('search');
        const statusSelect = document.getElementById('status');

        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(this.performSearch.bind(this), 300));
        }

        if (statusSelect) {
            statusSelect.addEventListener('change', this.performSearch.bind(this));
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', this.handleKeyboardShortcuts.bind(this));

        // Table row click handlers
        document.querySelectorAll('.job-row').forEach(row => {
            row.addEventListener('click', this.handleRowClick.bind(this));
            row.addEventListener('mouseenter', this.handleRowHover.bind(this));
            row.addEventListener('mouseleave', this.handleRowLeave.bind(this));
        });
    }

    addStatusAnimations() {
        // Add pulsing animation to running jobs
        document.querySelectorAll('.status-in-progress').forEach(element => {
            const row = element.closest('tr');
            if (row) {
                row.classList.add('job-running');
                
                // Add a subtle glow effect
                element.style.boxShadow = '0 0 10px rgba(0, 123, 255, 0.5)';
                element.style.animation = 'pulse 2s infinite';
            }
        });

        // Add success animation to completed jobs
        document.querySelectorAll('.status-completed').forEach(element => {
            element.style.transition = 'all 0.3s ease';
        });

        // Add error shake animation to failed jobs
        document.querySelectorAll('.status-error').forEach(element => {
            element.addEventListener('mouseenter', () => {
                element.style.animation = 'shake 0.5s ease-in-out';
            });
            element.addEventListener('animationend', () => {
                element.style.animation = '';
            });
        });
    }

    setupTooltips() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Custom tooltips for job rows
        document.querySelectorAll('.job-row').forEach(row => {
            const jobName = row.dataset.job;
            const statusElement = row.querySelector('.status-badge');
            const status = statusElement ? statusElement.textContent.trim() : '';
            
            row.setAttribute('title', `Job: ${jobName} | Status: ${status}`);
            row.setAttribute('data-bs-toggle', 'tooltip');
            row.setAttribute('data-bs-placement', 'top');
        });
    }

    enhanceTableInteractivity() {
        // Add sorting functionality
        document.querySelectorAll('th').forEach(header => {
            if (header.textContent.trim() !== '') {
                header.style.cursor = 'pointer';
                header.addEventListener('click', this.sortTable.bind(this, header));
                
                // Add sort indicator
                const sortIcon = document.createElement('i');
                sortIcon.className = 'fas fa-sort ms-2 text-muted';
                header.appendChild(sortIcon);
            }
        });

        // Add row selection
        document.querySelectorAll('.job-row').forEach(row => {
            row.addEventListener('click', (e) => {
                if (e.ctrlKey || e.metaKey) {
                    row.classList.toggle('selected');
                } else {
                    document.querySelectorAll('.job-row.selected').forEach(r => r.classList.remove('selected'));
                    row.classList.add('selected');
                }
            });
        });
    }

    performSearch() {
        const searchTerm = document.getElementById('search')?.value.toLowerCase() || '';
        const statusFilter = document.getElementById('status')?.value || '';
        
        let visibleCount = 0;
        
        document.querySelectorAll('.job-row').forEach(row => {
            const jobName = row.dataset.job?.toLowerCase() || '';
            const jobStatus = row.querySelector('.status-badge')?.textContent.trim() || '';
            
            const matchesSearch = !searchTerm || jobName.includes(searchTerm);
            const matchesStatus = !statusFilter || jobStatus === statusFilter;
            
            if (matchesSearch && matchesStatus) {
                row.style.display = '';
                row.style.animation = 'fadeIn 0.3s ease-in';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update results counter
        this.updateResultsCounter(visibleCount);
    }

    updateResultsCounter(count) {
        let counter = document.getElementById('results-counter');
        if (!counter) {
            counter = document.createElement('small');
            counter.id = 'results-counter';
            counter.className = 'text-muted ms-2';
            
            const header = document.querySelector('.card-header h5');
            if (header) {
                header.appendChild(counter);
            }
        }
        
        counter.textContent = `(${count} visible)`;
    }

    sortTable(header) {
        const table = header.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const columnIndex = Array.from(header.parentNode.children).indexOf(header);
        
        // Determine sort direction
        const currentSort = header.dataset.sort || 'asc';
        const newSort = currentSort === 'asc' ? 'desc' : 'asc';
        header.dataset.sort = newSort;
        
        // Update sort icons
        document.querySelectorAll('th i.fa-sort, th i.fa-sort-up, th i.fa-sort-down').forEach(icon => {
            icon.className = 'fas fa-sort ms-2 text-muted';
        });
        
        const sortIcon = header.querySelector('i');
        sortIcon.className = `fas fa-sort-${newSort === 'asc' ? 'up' : 'down'} ms-2 text-primary`;
        
        // Sort rows
        rows.sort((a, b) => {
            const aValue = a.children[columnIndex].textContent.trim();
            const bValue = b.children[columnIndex].textContent.trim();
            
            // Try to parse as numbers first
            const aNum = parseFloat(aValue);
            const bNum = parseFloat(bValue);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return newSort === 'asc' ? aNum - bNum : bNum - aNum;
            }
            
            // String comparison
            return newSort === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
        });
        
        // Reorder rows in DOM
        rows.forEach(row => tbody.appendChild(row));
        
        // Add animation
        rows.forEach((row, index) => {
            row.style.animation = `slideIn 0.3s ease-in ${index * 0.05}s both`;
        });
    }

    handleRowClick(event) {
        const row = event.currentTarget;
        const jobName = row.dataset.job;
        
        // Show job details modal or navigate to detail page
        this.showJobDetails(jobName, row);
    }

    handleRowHover(event) {
        const row = event.currentTarget;
        row.style.transform = 'scale(1.02)';
        row.style.zIndex = '10';
        row.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
    }

    handleRowLeave(event) {
        const row = event.currentTarget;
        row.style.transform = '';
        row.style.zIndex = '';
        row.style.boxShadow = '';
    }

    showJobDetails(jobName, row) {
        // Create or update job details modal
        let modal = document.getElementById('jobDetailsModal');
        if (!modal) {
            modal = this.createJobDetailsModal();
        }
        
        // Populate modal with job data
        const modalBody = modal.querySelector('.modal-body');
        const statusBadge = row.querySelector('.status-badge');
        const statusClass = statusBadge ? statusBadge.className : '';
        const status = statusBadge ? statusBadge.textContent.trim() : '';
        
        modalBody.innerHTML = `
            <div class="job-details-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">${jobName}</h5>
                    <span class="${statusClass}">${status}</span>
                </div>
                <div class="row g-3">
                    ${this.extractRowData(row)}
                </div>
            </div>
        `;
        
        // Show modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    createJobDetailsModal() {
        const modal = document.createElement('div');
        modal.id = 'jobDetailsModal';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Job Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        return modal;
    }

    extractRowData(row) {
        const cells = row.querySelectorAll('td');
        const headers = document.querySelectorAll('th');
        let html = '';
        
        cells.forEach((cell, index) => {
            if (headers[index] && index > 0) { // Skip first column (job name)
                const header = headers[index].textContent.trim();
                const value = cell.textContent.trim();
                
                html += `
                    <div class="col-md-6">
                        <strong>${header}:</strong> ${value}
                    </div>
                `;
            }
        });
        
        return html;
    }

    handleKeyboardShortcuts(event) {
        // Ctrl/Cmd + F: Focus search
        if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
            event.preventDefault();
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Ctrl/Cmd + R: Refresh
        if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
            event.preventDefault();
            window.location.reload();
        }
        
        // Escape: Clear search
        if (event.key === 'Escape') {
            const searchInput = document.getElementById('search');
            if (searchInput && searchInput.value) {
                searchInput.value = '';
                this.performSearch();
            }
        }
    }

    startAutoRefresh() {
        // Auto-refresh every 30 seconds
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                this.refreshData();
            }
        }, 30000);
    }

    refreshData() {
        // Show loading indicator
        this.showLoadingIndicator();
        
        // Reload page or fetch new data via AJAX
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    showLoadingIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'loading-indicator';
        indicator.className = 'position-fixed top-0 end-0 m-3 alert alert-info d-flex align-items-center';
        indicator.innerHTML = `
            <div class="loading-spinner me-2"></div>
            Refreshing data...
        `;
        
        document.body.appendChild(indicator);
        
        setTimeout(() => {
            indicator.remove();
        }, 2000);
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
    
    @keyframes slideIn {
        from { transform: translateY(-10px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .job-row.selected {
        background-color: rgba(0, 123, 255, 0.1) !important;
        border-left: 4px solid #007bff;
    }
    
    .job-details-card {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 1.5rem;
    }
`;
document.head.appendChild(style);

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new JobStatusDashboard();
});
