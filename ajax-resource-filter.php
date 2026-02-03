<?php
/**
 * Plugin Name: AJAX Resource Filter with Taxonomy
 * Description: Custom resource post type with AJAX filtering by taxonomy and search
 * Version: 1.0
 * Author: Towfique Elahe
 * Author URI: https://towfiqueelahe.com/
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register custom post type and taxonomies
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

    // Register Resource Year taxonomy
    register_taxonomy('resource-year', 'resource', [
        'labels' => [
            'name' => 'Years',
            'singular_name' => 'Year',
            'search_items' => 'Search Years',
            'all_items' => 'All Years',
            'parent_item' => 'Parent Year',
            'parent_item_colon' => 'Parent Year:',
            'edit_item' => 'Edit Year',
            'update_item' => 'Update Year',
            'add_new_item' => 'Add New Year',
            'new_item_name' => 'New Year Name',
        ],
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'resource-year'],
        'show_in_rest' => true,
    ]);

    // Register Resource Model taxonomy
    register_taxonomy('resource-model', 'resource', [
        'labels' => [
            'name' => 'Models',
            'singular_name' => 'Model',
            'search_items' => 'Search Models',
            'all_items' => 'All Models',
            'parent_item' => 'Parent Model',
            'parent_item_colon' => 'Parent Model:',
            'edit_item' => 'Edit Model',
            'update_item' => 'Update Model',
            'add_new_item' => 'Add New Model',
            'new_item_name' => 'New Model Name',
        ],
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'resource-model'],
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
    // Add taxonomies to REST response
    register_rest_field('resource', 'resource_years', [
        'get_callback' => function($object) {
            $terms = get_the_terms($object['id'], 'resource-year');
            if (empty($terms) || is_wp_error($terms)) {
                return [];
            }
            return array_map(function ($t) {
                return [
                    'id' => $t->term_id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ];
            }, $terms);
        },
        'schema' => ['type' => 'array'],
    ]);

    register_rest_field('resource', 'resource_models', [
        'get_callback' => function($object) {
            $terms = get_the_terms($object['id'], 'resource-model');
            if (empty($terms) || is_wp_error($terms)) {
                return [];
            }
            return array_map(function ($t) {
                return [
                    'id' => $t->term_id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ];
            }, $terms);
        },
        'schema' => ['type' => 'array'],
    ]);

    // Add featured image URL
    register_rest_field('resource', 'featured_image_url', [
        'get_callback' => function($object) {
            $image_id = get_post_thumbnail_id($object['id']);
            return $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
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
            <div class="rf-searchbar">
                <form id="rfSearchForm" method="get" action="<?php echo esc_url(home_url('/resources/')); ?>">
                    <input type="text" name="c" id="rfSearchInput" placeholder="Search resources..."
                        value="<?php echo esc_attr($search_term); ?>" />
                    <button type="submit">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                </form>
            </div>
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

                <div class="rf-filter-container">
                    <!-- Year Filter -->
                    <div class="rf-fgroup" data-group="year">
                        <div class="rf-fhead">
                            Year
                            <span class="rf-muted">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="open">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </div>
                        <div class="rf-fbody open" data-role="year-options">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Model Filter -->
                    <div class="rf-fgroup" data-group="model">
                        <div class="rf-fhead">
                            Model
                            <span class="rf-muted">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="open">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </span>
                        </div>
                        <div class="rf-fbody open" data-role="model-options">
                            <!-- Will be populated by JavaScript -->
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
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.rf-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.rf-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.rf-heading {
    font-size: 28px;
    font-weight: 600;
    margin: 0;
}

.rf-searchbar {
    flex: 1;
    max-width: 400px;
}

.rf-searchbar form {
    position: relative;
}

.rf-searchbar input {
    width: 100%;
    padding: 12px 50px 12px 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
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
    color: #666;
}

.rf-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 40px;
}

.rf-sidebar {
    border-right: 1px solid #eee;
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
    border: 1px solid #ddd;
    padding: 4px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.rf-fgroup {
    margin-bottom: 20px;
}

.rf-fhead {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    cursor: pointer;
    font-weight: 600;
    border-bottom: 1px solid #eee;
}

.rf-fbody {
    padding: 10px 0;
    max-height: 300px;
    overflow-y: auto;
}

.rf-fbody:not(.open) {
    display: none;
}

.rf-check {
    display: flex;
    align-items: center;
    padding: 8px 0;
    cursor: pointer;
}

.rf-check input {
    margin-right: 10px;
}

.rf-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.rf-count-wrapper {
    display: flex;
    align-items: center;
    gap: 15px;
}

.rf-filter-toggle {
    display: none;
    background: none;
    border: 1px solid #ddd;
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
}

.rf-count {
    font-size: 14px;
    color: #666;
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
    border: 1px solid #ddd;
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
    border: 1px solid #eee;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
}

.rf-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.rf-card-image {
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
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 10px 0;
}

.rf-card-title a {
    color: inherit;
    text-decoration: none;
}

.rf-card-title a:hover {
    color: #0073aa;
}

.rf-card-excerpt {
    color: #666;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 15px;
}

.rf-card-meta {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: #999;
    margin-top: 15px;
}

.rf-pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 40px;
}

.rf-pagebtn {
    padding: 8px 15px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 6px;
    cursor: pointer;
}

.rf-pagebtn.active {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
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
    color: #999;
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
        background: white;
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Main Resource Filter Component
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

            this.resources = [];
            this.state = {
                page: 1,
                perPage: parseInt('<?php echo $atts["posts_per_page"]; ?>') || 12,
                sort: 'date_desc',
                pages: 1,
                searchTerm: '<?php echo esc_js($search_term); ?>'
            };

            this.init();
        }

        init() {
            this.setupEventListeners();
            this.fetchResources();
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

            // Filter changes
            if (this.sidebar) {
                this.sidebar.addEventListener('change', (e) => {
                    if (e.target.matches('input[type="checkbox"]')) {
                        this.state.page = 1;
                        this.render();
                    }
                });
            }

            // Clear filters
            this.root.addEventListener('click', (e) => {
                if (e.target.closest('[data-action="clear"]')) {
                    this.clearFilters();
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

            // Collapsible filter groups
            this.root.querySelectorAll('.rf-fhead').forEach(head => {
                head.addEventListener('click', () => {
                    const body = head.parentElement.querySelector('.rf-fbody');
                    const icon = head.querySelector('svg');
                    body.classList.toggle('open');
                    if (icon) {
                        icon.style.transform = body.classList.contains('open') ?
                            'translateY(-50%) rotate(180deg)' :
                            'translateY(-50%)';
                    }
                });
            });
        }

        getFilters() {
            const filters = {};
            ['year', 'model'].forEach(group => {
                const inputs = this.sidebar.querySelectorAll(`input[name="${group}"]:checked`);
                filters[group] = Array.from(inputs).map(input => input.value);
            });
            return filters;
        }

        applyFilters(resources) {
            const filters = this.getFilters();

            return resources.filter(resource => {
                // Year filter
                if (filters.year.length > 0) {
                    const resourceYears = resource.years.map(y => y.name);
                    if (!filters.year.some(year => resourceYears.includes(year))) {
                        return false;
                    }
                }

                // Model filter
                if (filters.model.length > 0) {
                    const resourceModels = resource.models.map(m => m.name);
                    if (!filters.model.some(model => resourceModels.includes(model))) {
                        return false;
                    }
                }

                return true;
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

        createResourceCard(resource) {
            const years = resource.years.map(y => y.name).join(', ');
            const models = resource.models.map(m => m.name).join(', ');
            const date = new Date(resource.date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            return `
                    <article class="rf-card">
                        ${resource.image ? `
                            <div class="rf-card-image">
                                <img src="${resource.image}" alt="${resource.title}">
                            </div>
                        ` : ''}
                        <div class="rf-card-content">
                            <h3 class="rf-card-title">
                                <a href="${resource.link}">${resource.title}</a>
                            </h3>
                            <div class="rf-card-excerpt">${resource.excerpt}</div>
                            <div class="rf-card-meta">
                                ${years ? `<span>Year: ${years}</span>` : ''}
                                ${models ? `<span>Model: ${models}</span>` : ''}
                                <span>${date}</span>
                            </div>
                        </div>
                    </article>
                `;
        }

        render() {
            let filtered = this.applyFilters(this.resources);

            // Apply search if exists
            if (this.state.searchTerm) {
                const term = this.state.searchTerm.toLowerCase();
                filtered = filtered.filter(resource =>
                    resource.title.toLowerCase().includes(term) ||
                    resource.excerpt.toLowerCase().includes(term)
                );
            }

            filtered = this.sortResources(filtered);
            const pagination = this.paginate(filtered);
            this.state.pages = pagination.pages;

            // Update results
            this.resultsEl.innerHTML = pagination.items.length > 0 ?
                pagination.items.map(resource => this.createResourceCard(resource)).join('') :
                '<div class="rf-muted">No resources found matching your criteria.</div>';

            // Update count
            if (this.countEl) {
                this.countEl.textContent =
                    `${pagination.total} resource${pagination.total === 1 ? '' : 's'}`;
            }

            // Update pagination
            this.renderPagination(pagination);
        }

        clearFilters() {
            // Uncheck all filter checkboxes
            this.sidebar.querySelectorAll('input[type="checkbox"]:checked').forEach(input => {
                input.checked = false;
            });

            // Reset to first page
            this.state.page = 1;

            // Clear search if we're on search page
            const searchForm = this.root.querySelector('#rfSearchForm');
            const searchInput = this.root.querySelector('#rfSearchInput');

            if (window.location.search.includes('c=')) {
                // If we have search term in URL, redirect to clean archive
                window.location.href = this.root.dataset.archiveUrl;
            } else {
                // Otherwise just re-render
                this.render();
            }
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
                    years: resource.resource_years || [],
                    models: resource.resource_models || []
                }));

                // Populate filter options
                this.populateFilterOptions();
                this.render();

            } catch (error) {
                console.error('Error fetching resources:', error);
                this.resultsEl.innerHTML =
                    '<div class="rf-muted">Unable to load resources. Please try again later.</div>';
            }
        }

        populateFilterOptions() {
            // Collect all unique years and models
            const allYears = new Set();
            const allModels = new Set();

            this.resources.forEach(resource => {
                resource.years.forEach(year => allYears.add(year.name));
                resource.models.forEach(model => allModels.add(model.name));
            });

            // Populate year filter
            const yearContainer = this.sidebar.querySelector('[data-role="year-options"]');
            if (yearContainer) {
                const sortedYears = Array.from(allYears).sort();
                yearContainer.innerHTML = sortedYears.map(year => `
                        <label class="rf-check">
                            <input type="checkbox" name="year" value="${year}">
                            ${year}
                        </label>
                    `).join('');
            }

            // Populate model filter
            const modelContainer = this.sidebar.querySelector('[data-role="model-options"]');
            if (modelContainer) {
                const sortedModels = Array.from(allModels).sort();
                modelContainer.innerHTML = sortedModels.map(model => `
                        <label class="rf-check">
                            <input type="checkbox" name="model" value="${model}">
                            ${model}
                        </label>
                    `).join('');
            }
        }
    }

    // Initialize all resource filters on page
    document.querySelectorAll('.rf-wrap').forEach(wrap => {
        new ResourceFilter(wrap);
    });

    // Search form handler
    const searchForm = document.querySelector('#rfSearchForm');
    const searchInput = document.querySelector('#rfSearchInput');

    if (searchForm && searchInput) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const term = searchInput.value.trim();
            const archiveUrl = document.querySelector('.rf-wrap')?.dataset.archiveUrl || '/resources/';

            if (term) {
                window.location.href = `${archiveUrl}?c=${encodeURIComponent(term)}`;
            } else {
                window.location.href = archiveUrl;
            }
        });
    }
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
        // Fallback to shortcode in content
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
?>