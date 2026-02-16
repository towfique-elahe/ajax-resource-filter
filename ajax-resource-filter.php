<?php
/**
 * Plugin Name: AJAX Resource Filter with Taxonomy
 * Description: Custom resource post type with AJAX filtering by hierarchical taxonomy (Make → Model → Year) - Single Selection
 * Version: 2.3
 * Author: Towfique Elahe
 * Author URI: https://towfiqueelahe.com/
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register custom post type and hierarchical taxonomy
add_action('init', 'ajax_resource_filter_register_cpt');
function ajax_resource_filter_register_cpt() {
    // Register Resource post type
    register_post_type('resource', [
        'labels' => [
            'name' => 'Resources',
            'singular_name' => 'Resource',
            'menu_name' => 'Resources',
            'all_items' => 'All Resources',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Resource',
            'edit_item' => 'Edit Resource',
            'new_item' => 'New Resource',
            'view_item' => 'View Resource',
            'search_items' => 'Search Resources',
        ],
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'resources'],
        'menu_icon' => 'dashicons-portfolio',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest' => true,
    ]);

    // Register hierarchical Resource Category taxonomy (Make → Model → Year)
    register_taxonomy('resource-category', 'resource', [
        'labels' => [
            'name' => 'Resource Categories',
            'singular_name' => 'Resource Category',
            'search_items' => 'Search Categories',
            'all_items' => 'All Categories',
            'parent_item' => 'Parent Make',
            'parent_item_colon' => 'Parent Make:',
            'edit_item' => 'Edit Category',
            'update_item' => 'Update Category',
            'add_new_item' => 'Add New Category',
            'new_item_name' => 'New Category Name',
            'menu_name' => 'Categories',
        ],
        'hierarchical' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'resource-category', 'hierarchical' => true],
        'show_in_rest' => true,
    ]);
}

// Add rewrite rules for search
add_action('init', 'ajax_resource_filter_rewrite_rules');
function ajax_resource_filter_rewrite_rules() {
    add_rewrite_rule('^resources/search/([^/]+)/?$', 'index.php?post_type=resource&s=$matches[1]', 'top');
    add_rewrite_tag('%resource_search%', '([^&]+)');
}

// Flush rewrite rules on activation
register_activation_hook(__FILE__, 'ajax_resource_filter_activate');
function ajax_resource_filter_activate() {
    ajax_resource_filter_register_cpt();
    flush_rewrite_rules();
}

// Register REST API fields
add_action('rest_api_init', 'ajax_resource_filter_register_rest_fields');
function ajax_resource_filter_register_rest_fields() {
    // Add hierarchical taxonomy data to REST response
    register_rest_field('resource', 'resource_categories', [
        'get_callback' => function($object) {
            $terms = get_the_terms($object['id'], 'resource-category');
            if (empty($terms) || is_wp_error($terms)) {
                return [];
            }
            
            $formatted_terms = [];
            foreach ($terms as $term) {
                // Get the full hierarchy for this term
                $hierarchy = [];
                $current_term = $term;
                
                // Build path from term up to root
                while ($current_term) {
                    $hierarchy[] = [
                        'id' => $current_term->term_id,
                        'name' => $current_term->name,
                        'slug' => $current_term->slug,
                        'parent' => $current_term->parent
                    ];
                    
                    if ($current_term->parent) {
                        $current_term = get_term($current_term->parent, 'resource-category');
                    } else {
                        $current_term = null;
                    }
                }
                
                // Reverse to get Make → Model → Year order
                $formatted_terms[] = array_reverse($hierarchy);
            }
            
            return $formatted_terms;
        },
        'schema' => ['type' => 'array'],
    ]);

    // Add all taxonomy terms for easier searching
    register_rest_field('resource', 'all_category_names', [
        'get_callback' => function($object) {
            $terms = get_the_terms($object['id'], 'resource-category');
            if (empty($terms) || is_wp_error($terms)) {
                return [];
            }
            
            $names = [];
            foreach ($terms as $term) {
                $names[] = $term->name;
            }
            return $names;
        },
        'schema' => ['type' => 'array'],
    ]);

    // Add featured image URL
    register_rest_field('resource', 'featured_image_url', [
        'get_callback' => function($object) {
            $image_id = get_post_thumbnail_id($object['id']);
            return $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
        },
        'schema' => ['type' => 'string'],
    ]);

    // Add excerpt
    register_rest_field('resource', 'excerpt_plain', [
        'get_callback' => function($object) {
            return wp_strip_all_tags(get_the_excerpt($object['id']));
        },
        'schema' => ['type' => 'string'],
    ]);
}

// Shortcode for resource filter
add_shortcode('resource_filter', 'ajax_resource_filter_shortcode');
function ajax_resource_filter_shortcode($atts) {
    $atts = shortcode_atts([
        'posts_per_page' => 12,
    ], $atts);

    $uid = uniqid('rf_');
    $search_term = isset($_GET['c']) ? sanitize_text_field($_GET['c']) : '';
    
    ob_start(); ?>
<div id="<?php echo esc_attr($uid); ?>" class="rf-wrap"
    data-archive-url="<?php echo esc_url(home_url('/resources/')); ?>">
    <div class="rf-container">
        <div class="rf-head">
            <h3 class="rf-heading">
                <?php echo $search_term ? 'Search results for "' . esc_html($search_term) . '"' : 'Browse All Resources'; ?>
            </h3>
        </div>

        <div class="rf-grid">
            <aside class="rf-sidebar">
                <h4 class="rf-sidebar-heading">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        Filter by
                    </span>
                    <button data-action="clear">Clear filters</button>
                </h4>

                <!-- Search in sidebar -->
                <div class="rf-searchbar">
                    <form id="rfSearchForm" method="get" action="<?php echo esc_url(home_url('/resources/')); ?>">
                        <input type="text" name="c" id="rfSearchInput" placeholder="Search resources..."
                            value="<?php echo esc_attr($search_term); ?>" />
                        <button type="submit">
                            <i aria-hidden="true" class="jki jki-search-solid"></i>
                        </button>
                    </form>
                </div>

                <div class="rf-filter-container">
                    <!-- Make Filter (Level 1) - Radio Buttons -->
                    <div class="rf-fgroup" data-group="make">
                        <div class="rf-fhead" data-toggle="make-options">
                            <span>Car Make</span>
                            <svg class="rf-fhead-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                        <div class="rf-fbody" data-role="make-options">
                            <!-- Will be populated by JavaScript with parent terms only -->
                        </div>
                    </div>

                    <!-- Model Filter (Level 2) - Radio Buttons -->
                    <div class="rf-fgroup" data-group="model">
                        <div class="rf-fhead" data-toggle="model-options">
                            <span>Car Model</span>
                            <svg class="rf-fhead-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                        <div class="rf-fbody" data-role="model-options">
                            <!-- Will be populated dynamically based on selected make -->
                        </div>
                    </div>

                    <!-- Year Filter (Level 3) - Radio Buttons -->
                    <div class="rf-fgroup" data-group="year">
                        <div class="rf-fhead" data-toggle="year-options">
                            <span>Year of Make</span>
                            <svg class="rf-fhead-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                        <div class="rf-fbody" data-role="year-options">
                            <!-- Will be populated dynamically based on selected make and model -->
                        </div>
                    </div>
                </div>
            </aside>

            <main class="rf-main">
                <div class="rf-toolbar">
                    <div class="rf-count-wrapper">
                        <button class="rf-filter-toggle" aria-label="Toggle filters">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>
                        </button>
                        <div class="rf-count">Loading resources...</div>
                    </div>

                    <div class="rf-sort-wrapper">
                        <label for="<?php echo esc_attr($uid); ?>_sort" class="rf-muted">Sort by</label>
                        <div class="rf-select">
                            <select id="<?php echo esc_attr($uid); ?>_sort" class="rf-sort">
                                <option value="date_desc">Newest First</option>
                                <option value="date_asc">Oldest First</option>
                                <option value="title_asc">Title A-Z</option>
                                <option value="title_desc">Title Z-A</option>
                            </select>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="rf-select-icon">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                    </div>
                </div>

                <div id="<?php echo esc_attr($uid); ?>_results" class="rf-results"></div>

                <nav class="rf-pagination" id="<?php echo esc_attr($uid); ?>_pagination"></nav>
            </main>
        </div>
    </div>
    <div class="rf-backdrop"></div>
</div>

<style>
.rf-wrap {
    font-family: var(--e-global-typography-text-font-family), Sans-serif;
}

.rf-container {
    width: 100%;
    margin: 0 auto;
}

.rf-head {
    margin-bottom: 30px;
}

.rf-heading {
    color: var(--e-global-color-primary);
    font-size: 28px;
    font-weight: 600;
    margin: 0;
}

.rf-searchbar {
    margin-bottom: 20px;
}

.rf-searchbar form {
    position: relative;
}

.rf-searchbar input {
    width: 100%;
    padding: 12px 50px 12px 20px;
    outline: none;
    border: 1px solid var(--e-global-color-02b06ea);
    border-radius: 8px;
    font-size: 16px;
    background-color: var(--e-global-color-7e5b33b);
    color: var(--e-global-color-18fbe39);
}

.rf-searchbar input:focus {
    border-color: var(--e-global-color-primary);
}

.rf-searchbar button {
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    background: none;
    border: none;
    padding: 0 15px;
    cursor: pointer;
    color: var(--e-global-color-18fbe39);
}

.rf-searchbar button:hover {
    background: var(--e-global-color-primary);
}

.rf-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 40px;
}

.rf-sidebar {
    color: var(--e-global-color-text);
    border-right: 1px solid var(--e-global-color-02b06ea);
    padding-right: 20px;
}

.rf-sidebar-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 18px;
    margin: 0 0 20px 0;
}

.rf-sidebar-heading button {
    background: none;
    border: 1px solid var(--e-global-color-02b06ea);
    color: var(--e-global-color-text);
    padding: 4px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.rf-sidebar-heading button:hover {
    background: var(--e-global-color-primary);
    color: var(--e-global-color-18fbe39);
    border-color: var(--e-global-color-primary);
}

.rf-fgroup {
    margin-bottom: 15px;
    border: 1px solid var(--e-global-color-02b06ea);
    border-radius: 8px;
    overflow: hidden;
}

.rf-fhead {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    cursor: pointer;
    font-weight: 600;
    background-color: var(--e-global-color-7e5b33b);
    user-select: none;
}

.rf-fhead:hover {
    background-color: var(--e-global-color-02b06ea);
}

.rf-fhead-icon {
    transition: transform 0.3s ease;
}

.rf-fgroup.open .rf-fhead-icon {
    transform: rotate(180deg);
}

.rf-fbody {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background-color: var(--e-global-color-7e5b33b);
}

.rf-fgroup.open .rf-fbody {
    max-height: 300px;
    overflow-y: auto;
    padding: 5px 0;
}

.rf-radio {
    display: flex;
    align-items: center;
    padding: 8px 15px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.rf-radio:hover {
    background-color: var(--e-global-color-02b06ea);
}

.rf-radio input[type="radio"] {
    accent-color: var(--e-global-color-primary);
    margin-right: 10px;
    cursor: pointer;
}

.rf-radio.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.rf-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--e-global-color-02b06ea);
}

.rf-count-wrapper {
    display: flex;
    align-items: center;
    gap: 15px;
}

.rf-filter-toggle {
    display: none;
    background: none;
    color: var(--e-global-color-18fbe39);
    border: 1px solid var(--e-global-color-02b06ea);
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
}

.rf-count {
    font-size: 14px;
    color: var(--e-global-color-text);
}

.rf-sort-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rf-select {
    position: relative;
}

.rf-select select {
    appearance: none;
    padding: 8px 35px 8px 15px;
    outline: none;
    background-color: var(--e-global-color-7e5b33b);
    color: var(--e-global-color-18fbe39);
    border: 1px solid var(--e-global-color-02b06ea);
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
}

.rf-select-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}

.rf-results {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.rf-card {
    border: 1px solid var(--e-global-color-02b06ea);
    border-radius: 12px;
    overflow: hidden;
    transition: ease .3s;
}

.rf-card:hover {
    border-color: var(--e-global-color-primary);
}

.rf-card-image {
    display: block;
    height: 180px;
    overflow: hidden;
}

.rf-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.rf-card-content {
    padding: 20px;
}

.rf-card-title {
    color: var(--e-global-color-18fbe39);
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 10px 0;
}

.rf-card-title:hover {
    color: var(--e-global-color-primary);
}

.rf-card-title a {
    color: inherit;
    text-decoration: none;
}

.rf-card-excerpt {
    color: var(--e-global-color-text);
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 15px;
}

.rf-card-meta {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    font-size: 12px;
    margin-top: 15px;
}

.rf-card-meta span {
    background-color: var(--e-global-color-primary);
    color: var(--e-global-color-18fbe39);
    padding: 5px 10px;
    border-radius: 4px;
}

.rf-pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 40px;
}

.rf-pagebtn {
    padding: 8px 15px;
    border: 1px solid var(--e-global-color-02b06ea);
    background: var(--e-global-color-7e5b33b);
    border-radius: 6px;
    cursor: pointer;
}

.rf-pagebtn.active {
    background: var(--e-global-color-primary);
    color: white;
    border-color: var(--e-global-color-primary);
}

.rf-pagebtn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.rf-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
}

.rf-muted {
    color: var(--e-global-color-text);
}

@media (max-width: 992px) {
    .rf-grid {
        grid-template-columns: 1fr;
    }

    .rf-sidebar {
        position: fixed;
        top: 0;
        left: -100%;
        width: 280px;
        height: 100%;
        background: var(--e-global-color-7e5b33b);
        z-index: 1000;
        padding: 20px;
        transition: left 0.3s;
        overflow-y: auto;
        border-right: none;
    }

    .rf-sidebar.open {
        left: 0;
    }

    .rf-filter-toggle {
        display: block;
    }

    .rf-backdrop.active {
        display: block;
    }
}

@media (max-width: 767px) {
    .rf-head {
        flex-direction: column;
    }

    .rf-sort-wrapper .rf-muted {
        display: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Main Resource Filter Component with Hierarchical Taxonomy - Single Selection (No "All" options)
    class ResourceFilter {
        constructor(root) {
            this.root = root;
            this.resultsEl = root.querySelector('[id$="_results"]');
            this.sortEl = root.querySelector('[id$="_sort"]');
            this.pagEl = root.querySelector('[id$="_pagination"]');
            this.countEl = root.querySelector('.rf-count');
            this.sidebar = root.querySelector('.rf-sidebar');
            this.backdrop = root.querySelector('.rf-backdrop');
            this.filterToggle = root.querySelector('.rf-filter-toggle');
            this.searchInput = root.querySelector('#rfSearchInput');
            this.searchForm = root.querySelector('#rfSearchForm');
            this.heading = root.querySelector('.rf-heading');

            this.resources = [];
            this.taxonomyData = {
                makes: [], // Parent terms
                modelsByMake: {}, // Child terms by parent make
                yearsByModel: {} // Grandchild terms by parent model
            };

            this.state = {
                page: 1,
                perPage: parseInt('<?php echo $atts["posts_per_page"]; ?>') || 12,
                sort: 'date_desc',
                pages: 1,
                searchTerm: '<?php echo esc_js($search_term); ?>',
                selectedMake: '',
                selectedModel: '',
                selectedYear: ''
            };

            // Search debouncing
            this.searchTimeout = null;
            this.searchDelay = 300;

            this.init();
        }

        init() {
            this.setupEventListeners();
            this.fetchResources();

            if (this.state.searchTerm && this.heading) {
                this.heading.textContent = `Search results for "${this.state.searchTerm}"`;
            }
        }

        setupEventListeners() {
            // Sort
            if (this.sortEl) {
                this.sortEl.addEventListener('change', (e) => {
                    this.state.sort = e.target.value;
                    this.state.page = 1;
                    this.render();
                });
            }

            // Radio button changes
            if (this.sidebar) {
                this.sidebar.addEventListener('change', (e) => {
                    if (e.target.matches('input[name="make"]')) {
                        this.handleMakeChange(e.target.value);
                    }

                    if (e.target.matches('input[name="model"]')) {
                        this.handleModelChange(e.target.value);
                    }

                    if (e.target.matches('input[name="year"]')) {
                        this.handleYearChange(e.target.value);
                    }
                });
            }

            // Clear filters
            this.root.addEventListener('click', (e) => {
                if (e.target.closest('[data-action="clear"]')) {
                    this.clearFilters();
                }

                // Toggle filter sections
                const fhead = e.target.closest('.rf-fhead');
                if (fhead) {
                    const toggle = fhead.dataset.toggle;
                    if (toggle) {
                        const fgroup = fhead.closest('.rf-fgroup');
                        fgroup.classList.toggle('open');
                    }
                }

                // Pagination
                const btn = e.target.closest('.rf-pagebtn');
                if (btn && btn.dataset.page) {
                    const page = parseInt(btn.dataset.page);
                    if (!isNaN(page)) {
                        this.state.page = Math.max(1, Math.min(page, this.state.pages));
                        this.render();
                    }
                    return;
                }
            });

            // Filter toggle for mobile
            if (this.filterToggle && this.backdrop) {
                this.filterToggle.addEventListener('click', () => {
                    this.sidebar.classList.add('open');
                    this.backdrop.classList.add('active');
                });

                this.backdrop.addEventListener('click', () => {
                    this.sidebar.classList.remove('open');
                    this.backdrop.classList.remove('active');
                });
            }

            // Search input - FIXED: Proper search handling
            if (this.searchInput) {
                this.searchInput.addEventListener('input', (e) => {
                    this.handleSearchInput(e.target.value);
                });

                if (this.searchForm) {
                    this.searchForm.addEventListener('submit', (e) => {
                        e.preventDefault();
                        this.handleSearchInput(this.searchInput.value);
                    });
                }
            }
        }

        handleMakeChange(value) {
            this.state.selectedMake = value;
            this.state.selectedModel = ''; // Reset model
            this.state.selectedYear = ''; // Reset year
            this.state.page = 1;

            // Clear model and year selections
            this.clearRadioGroup('model');
            this.clearRadioGroup('year');

            // Update model options based on selected make
            this.updateModelOptions(value);

            // Update year options (will show years for models of selected make)
            this.updateYearOptions('');

            this.render();
        }

        handleModelChange(value) {
            this.state.selectedModel = value;
            this.state.selectedYear = ''; // Reset year
            this.state.page = 1;

            // Clear year selection
            this.clearRadioGroup('year');

            // Update year options based on selected model
            this.updateYearOptions(value);

            this.render();
        }

        handleYearChange(value) {
            this.state.selectedYear = value;
            this.state.page = 1;
            this.render();
        }

        clearRadioGroup(groupName) {
            const radios = this.sidebar.querySelectorAll(`input[name="${groupName}"]`);
            radios.forEach(radio => {
                radio.checked = false;
            });
        }

        handleSearchInput(term) {
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            this.searchTimeout = setTimeout(() => {
                this.state.searchTerm = term;
                this.state.page = 1;

                if (this.heading) {
                    if (term) {
                        this.heading.textContent = `Search results for "${term}"`;
                    } else {
                        this.heading.textContent = 'Browse All Resources';
                    }
                }

                this.updateBrowserUrl(term);
                this.render();
            }, this.searchDelay);
        }

        updateBrowserUrl(searchTerm) {
            const baseUrl = this.root.dataset.archiveUrl || window.location.pathname;
            let newUrl = baseUrl;

            if (searchTerm) {
                newUrl = `${baseUrl}?c=${encodeURIComponent(searchTerm)}`;
            }

            window.history.replaceState({}, '', newUrl);
        }

        getSelectedFilters() {
            return {
                make: this.state.selectedMake,
                model: this.state.selectedModel,
                year: this.state.selectedYear
            };
        }

        // FIXED: Improved search function to properly filter by title, excerpt, and category names
        resourceMatchesSearch(resource) {
            if (!this.state.searchTerm) return true;

            const term = this.state.searchTerm.toLowerCase().trim();
            if (term === '') return true;

            // Search in title
            if (resource.title.toLowerCase().includes(term)) {
                return true;
            }

            // Search in excerpt
            if (resource.excerpt.toLowerCase().includes(term)) {
                return true;
            }

            // Search in all category names
            if (resource.all_category_names) {
                for (const catName of resource.all_category_names) {
                    if (catName.toLowerCase().includes(term)) {
                        return true;
                    }
                }
            }

            return false;
        }

        applyFilters(resources) {
            const filters = this.getSelectedFilters();

            return resources.filter(resource => {
                // First apply search filter
                if (!this.resourceMatchesSearch(resource)) {
                    return false;
                }

                // If no category filters are selected, return true
                if (!filters.make && !filters.model && !filters.year) {
                    return true;
                }

                // Check category filters
                let matchesFilter = false;

                if (resource.categories && resource.categories.length > 0) {
                    resource.categories.forEach(categoryPath => {
                        if (categoryPath.length >= 3) {
                            const [make, model, year] = categoryPath;

                            const makeMatch = !filters.make || filters.make === make.name;
                            const modelMatch = !filters.model || filters.model === model
                                .name;
                            const yearMatch = !filters.year || filters.year === year.name;

                            if (makeMatch && modelMatch && yearMatch) {
                                matchesFilter = true;
                            }
                        }
                    });
                }

                return matchesFilter;
            });
        }

        sortResources(resources) {
            const sorted = [...resources];

            switch (this.state.sort) {
                case 'date_asc':
                    return sorted.sort((a, b) => new Date(a.date) - new Date(b.date));
                case 'title_asc':
                    return sorted.sort((a, b) => a.title.localeCompare(b.title));
                case 'title_desc':
                    return sorted.sort((a, b) => b.title.localeCompare(a.title));
                case 'date_desc':
                default:
                    return sorted.sort((a, b) => new Date(b.date) - new Date(a.date));
            }
        }

        paginate(resources) {
            const total = resources.length;
            const pages = Math.max(1, Math.ceil(total / this.state.perPage));

            if (this.state.page > pages) {
                this.state.page = pages;
            }

            const start = (this.state.page - 1) * this.state.perPage;
            const end = start + this.state.perPage;

            return {
                items: resources.slice(start, end),
                total,
                pages
            };
        }

        renderPagination(pagination) {
            if (pagination.pages <= 1) {
                this.pagEl.innerHTML = '';
                return;
            }

            const prevDisabled = this.state.page === 1 ? 'disabled' : '';
            const nextDisabled = this.state.page === pagination.pages ? 'disabled' : '';

            let start = Math.max(1, this.state.page - 2);
            let end = Math.min(pagination.pages, start + 4);
            start = Math.max(1, end - 4);

            let html = `
                <button class="rf-pagebtn" data-page="${this.state.page - 1}" ${prevDisabled}>
                    ← Previous
                </button>`;

            if (start > 1) {
                html += `<button class="rf-pagebtn" data-page="1">1</button>`;
                if (start > 2) html += '<span class="rf-ellipsis">…</span>';
            }

            for (let i = start; i <= end; i++) {
                html +=
                    `<button class="rf-pagebtn ${i === this.state.page ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }

            if (end < pagination.pages) {
                if (end < pagination.pages - 1) html += '<span class="rf-ellipsis">…</span>';
                html +=
                    `<button class="rf-pagebtn" data-page="${pagination.pages}">${pagination.pages}</button>`;
            }

            html += `
                <button class="rf-pagebtn" data-page="${this.state.page + 1}" ${nextDisabled}>
                    Next →
                </button>`;

            this.pagEl.innerHTML = html;
        }

        // FIXED: Show multiple years if a resource has multiple year terms
        createResourceCard(resource) {
            // Collect all makes, models, and years from all category paths
            const makes = new Set();
            const models = new Set();
            const years = new Set();

            if (resource.categories && resource.categories.length > 0) {
                resource.categories.forEach(categoryPath => {
                    if (categoryPath.length >= 1) {
                        makes.add(categoryPath[0].name);
                    }
                    if (categoryPath.length >= 2) {
                        models.add(categoryPath[1].name);
                    }
                    if (categoryPath.length >= 3) {
                        years.add(categoryPath[2].name);
                    }
                });
            }

            const makeList = Array.from(makes).join(', ');
            const modelList = Array.from(models).join(', ');
            const yearList = Array.from(years).sort((a, b) => b - a).join(', ');

            return `
                <article class="rf-card">
                    ${resource.image ? `
                        <a class="rf-card-image" href="${resource.link}">
                            <img src="${resource.image}" alt="${resource.title}">
                        </a>
                    ` : ''}
                    <div class="rf-card-content">
                        <h3 class="rf-card-title">
                            <a href="${resource.link}">${resource.title}</a>
                        </h3>
                        <div class="rf-card-excerpt">${resource.excerpt.length > 100 ? resource.excerpt.substring(0, 100) + '...' : resource.excerpt}</div>
                        <div class="rf-card-meta">
                            ${makeList ? `<span>Make: ${makeList}</span>` : ''}
                            ${modelList ? `<span>Model: ${modelList}</span>` : ''}
                            ${yearList ? `<span>Year: ${yearList}</span>` : ''}
                        </div>
                    </div>
                </article>
            `;
        }

        render() {
            let filtered = this.applyFilters(this.resources);

            filtered = this.sortResources(filtered);
            const pagination = this.paginate(filtered);
            this.state.pages = pagination.pages;

            this.resultsEl.innerHTML = pagination.items.length > 0 ?
                pagination.items.map(resource => this.createResourceCard(resource)).join('') :
                '<div class="rf-muted">No resources found matching your criteria.</div>';

            if (this.countEl) {
                const countText = this.state.searchTerm ?
                    `${pagination.total} result${pagination.total === 1 ? '' : 's'} for "${this.state.searchTerm}"` :
                    `${pagination.total} resource${pagination.total === 1 ? '' : 's'}`;
                this.countEl.textContent = countText;
            }

            this.renderPagination(pagination);
        }

        clearFilters() {
            // Clear all radio buttons
            this.clearRadioGroup('make');
            this.clearRadioGroup('model');
            this.clearRadioGroup('year');

            // Clear search input
            if (this.searchInput) {
                this.searchInput.value = '';
            }

            // Reset state
            this.state.searchTerm = '';
            this.state.selectedMake = '';
            this.state.selectedModel = '';
            this.state.selectedYear = '';
            this.state.page = 1;

            if (this.heading) {
                this.heading.textContent = 'Browse All Resources';
            }

            this.updateBrowserUrl('');

            // Reset filter options to show all
            this.updateModelOptions('');
            this.updateYearOptions('');

            this.render();
        }

        async fetchResources() {
            try {
                const response = await fetch('/wp-json/wp/v2/resource?per_page=100');
                const data = await response.json();

                this.resources = data.map(resource => ({
                    id: resource.id,
                    title: resource.title.rendered,
                    excerpt: resource.excerpt_plain || '',
                    link: resource.link,
                    date: resource.date,
                    image: resource.featured_image_url || '',
                    categories: resource.resource_categories || [],
                    all_category_names: resource.all_category_names || [] // For search
                }));

                this.buildTaxonomyData();
                this.populateMakeOptions();
                this.updateModelOptions('');
                this.updateYearOptions('');

                this.render();

            } catch (error) {
                console.error('Error fetching resources:', error);
                this.resultsEl.innerHTML =
                    '<div class="rf-muted">Unable to load resources. Please try again later.</div>';
            }
        }

        buildTaxonomyData() {
            const makes = new Set();
            const modelsByMake = {};
            const yearsByModel = {};

            this.resources.forEach(resource => {
                if (resource.categories) {
                    resource.categories.forEach(categoryPath => {
                        if (categoryPath.length >= 1) {
                            const make = categoryPath[0];
                            makes.add(make.name);

                            if (categoryPath.length >= 2) {
                                const model = categoryPath[1];

                                if (!modelsByMake[make.name]) {
                                    modelsByMake[make.name] = new Set();
                                }
                                modelsByMake[make.name].add(model.name);

                                if (categoryPath.length >= 3) {
                                    const year = categoryPath[2];

                                    if (!yearsByModel[model.name]) {
                                        yearsByModel[model.name] = new Set();
                                    }
                                    yearsByModel[model.name].add(year.name);
                                }
                            }
                        }
                    });
                }
            });

            this.taxonomyData = {
                makes: Array.from(makes).sort(),
                modelsByMake: Object.fromEntries(
                    Object.entries(modelsByMake).map(([key, value]) => [key, Array.from(value)
                    .sort()])
                ),
                yearsByModel: Object.fromEntries(
                    Object.entries(yearsByModel).map(([key, value]) => [key, Array.from(value).sort(
                        (a, b) => b - a)])
                )
            };
        }

        populateMakeOptions() {
            const makeContainer = this.sidebar.querySelector('[data-role="make-options"]');
            if (!makeContainer) return;

            let html = '';

            this.taxonomyData.makes.forEach(make => {
                html += `
                    <label class="rf-radio">
                        <input type="radio" name="make" value="${make}">
                        ${make}
                    </label>
                `;
            });

            makeContainer.innerHTML = html;
        }

        updateModelOptions(selectedMake) {
            const modelContainer = this.sidebar.querySelector('[data-role="model-options"]');
            if (!modelContainer) return;

            let html = '';

            if (selectedMake && this.taxonomyData.modelsByMake[selectedMake]) {
                // Show only models for the selected make
                this.taxonomyData.modelsByMake[selectedMake].forEach(model => {
                    html += `
                        <label class="rf-radio">
                            <input type="radio" name="model" value="${model}">
                            ${model}
                        </label>
                    `;
                });
            } else {
                // Show all models if no make selected
                const allModels = new Set();
                Object.values(this.taxonomyData.modelsByMake).forEach(models => {
                    models.forEach(model => allModels.add(model));
                });

                Array.from(allModels).sort().forEach(model => {
                    html += `
                        <label class="rf-radio">
                            <input type="radio" name="model" value="${model}">
                            ${model}
                        </label>
                    `;
                });
            }

            modelContainer.innerHTML = html;
        }

        updateYearOptions(selectedModel) {
            const yearContainer = this.sidebar.querySelector('[data-role="year-options"]');
            if (!yearContainer) return;

            let html = '';

            if (selectedModel && this.taxonomyData.yearsByModel[selectedModel]) {
                // Show only years for the selected model
                this.taxonomyData.yearsByModel[selectedModel].forEach(year => {
                    html += `
                        <label class="rf-radio">
                            <input type="radio" name="year" value="${year}">
                            ${year}
                        </label>
                    `;
                });
            } else {
                // Show all years if no model selected
                const allYears = new Set();
                Object.values(this.taxonomyData.yearsByModel).forEach(years => {
                    years.forEach(year => allYears.add(year));
                });

                Array.from(allYears).sort((a, b) => b - a).forEach(year => {
                    html += `
                        <label class="rf-radio">
                            <input type="radio" name="year" value="${year}">
                            ${year}
                        </label>
                    `;
                });
            }

            yearContainer.innerHTML = html;
        }
    }

    // Initialize all resource filters on page
    document.querySelectorAll('.rf-wrap').forEach(wrap => {
        new ResourceFilter(wrap);
    });
});
</script>
<?php
    return ob_get_clean();
}

// Archive template override
add_filter('archive_template', 'ajax_resource_filter_archive_template');
function ajax_resource_filter_archive_template($template) {
    if (is_post_type_archive('resource')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/archive-resource.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        add_filter('the_content', 'ajax_resource_filter_archive_content');
    }
    return $template;
}

function ajax_resource_filter_archive_content($content) {
    if (is_post_type_archive('resource') && in_the_loop()) {
        return do_shortcode('[resource_filter]');
    }
    return $content;
}

// Add shortcode support in widgets
add_filter('widget_text', 'do_shortcode');