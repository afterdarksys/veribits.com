<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - VeriBits</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <nav>
        <div class="container" style="display: flex; align-items: center; gap: 2rem;">
            <a href="/" class="logo">VeriBits</a>

            <div class="nav-search" style="flex: 1; max-width: 500px; position: relative;">
                <input
                    type="text"
                    id="tool-search"
                    placeholder="Search tools..."
                    autocomplete="off"
                    style="width: 100%; padding: 0.625rem 1rem; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 6px; color: white; font-size: 0.9rem;"
                />
                <button
                    id="search-go"
                    onclick="performToolSearch()"
                    style="position: absolute; right: 4px; top: 50%; transform: translateY(-50%); padding: 0.4rem 1rem; background: var(--primary-color); border: none; border-radius: 4px; color: white; cursor: pointer; font-weight: 600; font-size: 0.85rem;"
                >
                    Go
                </button>
                <div id="search-autocomplete" style="position: absolute; top: 100%; left: 0; right: 0; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 6px; margin-top: 0.25rem; display: none; z-index: 1000; max-height: 400px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);"></div>
            </div>

            <ul style="display: flex; list-style: none; gap: 1.5rem; margin: 0; align-items: center;">
                <li><a href="/tools.php">Tools</a></li>
                <li><a href="/cli.php">CLI</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/about.php">About</a></li>
                <li><a href="/login.php">Login</a></li>
                <li><a href="/signup.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section style="padding: 3rem 2rem; min-height: 60vh;">
        <div class="container">
            <div style="max-width: 900px; margin: 0 auto;">
                <h1 id="search-title" style="margin-bottom: 0.5rem;">Search Results</h1>
                <p id="search-subtitle" style="color: var(--text-secondary); margin-bottom: 2rem;">Loading results...</p>

                <div id="search-results"></div>

                <div id="no-results" style="display: none; text-align: center; padding: 3rem 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
                    <h2 style="margin-bottom: 1rem;">No tools found</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem;">Try different keywords or browse all tools</p>
                    <a href="/tools.php" class="btn btn-primary">View All Tools</a>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2025 VeriBits. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">
                A service from <a href="https://www.afterdarksys.com/" target="_blank" rel="noopener">After Dark Systems, LLC</a>
            </p>
            <p style="margin-top: 1rem;">
                <a href="/privacy.php" style="color: var(--text-secondary); margin: 0 1rem;">Privacy</a>
                <a href="/terms.php" style="color: var(--text-secondary); margin: 0 1rem;">Terms</a>
                <a href="/support.php" style="color: var(--text-secondary); margin: 0 1rem;">Support</a>
            </p>
        </div>
    </footer>

    <script>
        // Tool search autocomplete (same as homepage)
        let searchTimeout;
        const searchInput = document.getElementById('tool-search');
        const autocompleteDiv = document.getElementById('search-autocomplete');

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                autocompleteDiv.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(`/api/v1/tools/search?q=${encodeURIComponent(query)}`);
                    const result = await response.json();

                    if (result.success && result.data.tools && result.data.tools.length > 0) {
                        displayAutocomplete(result.data.tools);
                    } else {
                        autocompleteDiv.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Search error:', error);
                }
            }, 200);
        });

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performToolSearch();
            }
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-search')) {
                autocompleteDiv.style.display = 'none';
            }
        });

        function displayAutocomplete(tools) {
            let html = '';
            const maxResults = Math.min(tools.length, 8);
            for (let i = 0; i < maxResults; i++) {
                const tool = tools[i];
                html += `
                    <div class="autocomplete-item" onclick="navigateToTool('${tool.url}')" style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='rgba(251, 191, 36, 0.1)'" onmouseout="this.style.background='transparent'">
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.25rem;">${tool.name}</div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">${tool.description}</div>
                        <div style="margin-top: 0.25rem; font-size: 0.75rem; color: var(--primary-color);">${tool.category}</div>
                    </div>
                `;
            }
            if (tools.length > maxResults) {
                html += `<div style="padding: 0.75rem 1rem; text-align: center; color: var(--text-secondary); font-size: 0.85rem;">+${tools.length - maxResults} more results - click Go to see all</div>`;
            }
            autocompleteDiv.innerHTML = html;
            autocompleteDiv.style.display = 'block';
        }

        function navigateToTool(path) {
            window.location.href = path;
        }

        async function performToolSearch() {
            const query = searchInput.value.trim();

            if (!query) {
                return;
            }

            if (query.length < 2) {
                alert('Please enter at least 2 characters');
                return;
            }

            // Navigate to search page with new query
            window.location.href = `/search.php?q=${encodeURIComponent(query)}`;
        }

        // Load search results on page load
        async function loadSearchResults() {
            const urlParams = new URLSearchParams(window.location.search);
            const query = urlParams.get('q');

            if (!query) {
                document.getElementById('search-title').textContent = 'Search Tools';
                document.getElementById('search-subtitle').textContent = 'Enter a search term to find tools';
                document.getElementById('search-results').innerHTML = '';
                return;
            }

            // Set search input value
            searchInput.value = query;

            try {
                const response = await fetch(`/api/v1/tools/search?q=${encodeURIComponent(query)}`);
                const result = await response.json();

                if (result.success && result.data.tools && result.data.tools.length > 0) {
                    displayResults(query, result.data.tools);
                } else {
                    showNoResults(query);
                }
            } catch (error) {
                console.error('Search error:', error);
                document.getElementById('search-subtitle').textContent = 'Error loading results. Please try again.';
            }
        }

        function displayResults(query, tools) {
            document.getElementById('search-title').textContent = `Search Results for "${query}"`;
            document.getElementById('search-subtitle').textContent = `Found ${tools.length} tool${tools.length === 1 ? '' : 's'}`;

            let html = '<div style="display: grid; gap: 1rem;">';

            tools.forEach(tool => {
                html += `
                    <div class="feature-card" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onclick="window.location.href='${tool.url}'" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 16px rgba(0, 0, 0, 0.3)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                            <h3 style="margin: 0; color: var(--primary-color);">${tool.name}</h3>
                            <span style="font-size: 0.8rem; padding: 0.25rem 0.75rem; background: rgba(251, 191, 36, 0.1); color: var(--primary-color); border-radius: 12px; white-space: nowrap;">${tool.category}</span>
                        </div>
                        <p style="color: var(--text-secondary); margin-bottom: 1rem;">${tool.description}</p>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="font-size: 0.85rem; color: var(--text-secondary);">
                                <code style="background: rgba(255, 255, 255, 0.05); padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.8rem;">${tool.cli_command}</code>
                            </div>
                            <span style="color: var(--primary-color); font-size: 0.9rem;">→</span>
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            document.getElementById('search-results').innerHTML = html;
            document.getElementById('no-results').style.display = 'none';
        }

        function showNoResults(query) {
            document.getElementById('search-title').textContent = `No results for "${query}"`;
            document.getElementById('search-subtitle').textContent = '';
            document.getElementById('search-results').innerHTML = '';
            document.getElementById('no-results').style.display = 'block';
        }

        // Run search on page load
        loadSearchResults();
    </script>

    <script src="/assets/js/main.js"></script>
</body>
</html>
