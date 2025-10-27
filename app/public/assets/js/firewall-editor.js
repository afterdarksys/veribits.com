// Firewall Editor JavaScript
let currentFirewallType = 'iptables';
let firewallConfig = {
    type: 'iptables',
    chains: {},
    deviceName: '',
    rawConfig: ''
};

let editingRuleIndex = null;
let editingChain = null;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initializeFirewallTypeSelector();
    initializeUploadArea();
    initializeRuleForm();
});

// Firewall Type Selector
function initializeFirewallTypeSelector() {
    const buttons = document.querySelectorAll('.firewall-type-btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFirewallType = btn.dataset.type;
            firewallConfig.type = currentFirewallType;
            updateRuleFormForType();
        });
    });
}

// Upload Area
function initializeUploadArea() {
    const uploadArea = document.getElementById('config-upload-area');
    const fileInput = document.getElementById('config-file-input');
    const fileInfo = document.getElementById('config-file-info');

    uploadArea.addEventListener('click', () => {
        fileInput.click();
    });

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('drag-over');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('drag-over');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        handleConfigFile(e.dataTransfer.files[0]);
    });

    fileInput.addEventListener('change', (e) => {
        handleConfigFile(e.target.files[0]);
    });

    document.getElementById('upload-config-button').addEventListener('click', uploadConfig);
}

function handleConfigFile(file) {
    if (!file) return;

    document.getElementById('config-filename').textContent = file.name;
    document.getElementById('config-file-info').style.display = 'block';

    // Store file for upload
    window.selectedConfigFile = file;
}

async function uploadConfig() {
    if (!window.selectedConfigFile) return;

    const formData = new FormData();
    formData.append('config_file', window.selectedConfigFile);
    formData.append('firewall_type', currentFirewallType);

    const deviceName = document.getElementById('device-name').value;
    if (deviceName) {
        formData.append('device_name', deviceName);
        firewallConfig.deviceName = deviceName;
    }

    try {
        showAlert('Parsing configuration...', 'info');
        const response = await uploadFile('/api/v1/firewall/upload', formData);

        firewallConfig = response.data;
        renderFirewallRules();
        updateStatistics();
        closeModal('upload-modal');
        showAlert('Configuration loaded successfully!', 'success');
    } catch (error) {
        showAlert(error.message, 'error');
    }
}

// Render Firewall Rules
function renderFirewallRules() {
    const container = document.getElementById('firewall-rules');
    const emptyState = document.getElementById('empty-state');

    if (!firewallConfig.chains || Object.keys(firewallConfig.chains).length === 0) {
        emptyState.style.display = 'block';
        container.style.display = 'none';
        document.getElementById('stats-grid').style.display = 'none';
        document.getElementById('command-preview-section').style.display = 'none';
        return;
    }

    emptyState.style.display = 'none';
    container.style.display = 'block';
    document.getElementById('stats-grid').style.display = 'grid';
    document.getElementById('command-preview-section').style.display = 'block';

    let html = '';

    for (const [chainName, chainData] of Object.entries(firewallConfig.chains)) {
        const ruleCount = chainData.rules ? chainData.rules.length : 0;

        html += `
            <div class="chain-section" data-chain="${chainName}">
                <div class="chain-header" onclick="toggleChain('${chainName}')">
                    <h3>
                        ${chainName}
                        <span class="chain-badge">${ruleCount} rules</span>
                        ${chainData.policy ? `<span class="rule-target ${chainData.policy}">${chainData.policy}</span>` : ''}
                    </h3>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="chain-content" id="chain-${chainName}">
                    ${renderChainRules(chainName, chainData)}
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
    updateCommandPreview();
}

function renderChainRules(chainName, chainData) {
    if (!chainData.rules || chainData.rules.length === 0) {
        return `
            <div class="empty-state" style="padding: 2rem;">
                <p>No rules in this chain</p>
                <button class="btn btn-secondary" onclick="showAddRuleModal('${chainName}')" style="margin-top: 1rem;">
                    Add First Rule
                </button>
            </div>
        `;
    }

    let html = `
        <table class="rules-table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Target</th>
                    <th>Protocol</th>
                    <th>Source</th>
                    <th>Destination</th>
                    <th>Options</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
    `;

    chainData.rules.forEach((rule, index) => {
        html += `
            <tr draggable="true" data-index="${index}">
                <td>${index + 1}</td>
                <td><span class="rule-target ${rule.target}">${rule.target}</span></td>
                <td>${rule.protocol || 'all'}</td>
                <td>${rule.source || 'any'}</td>
                <td>${rule.destination || 'any'}</td>
                <td>${formatRuleOptions(rule)}</td>
                <td class="rule-actions">
                    <button class="btn btn-secondary" onclick="editRule('${chainName}', ${index})">Edit</button>
                    <button class="btn btn-secondary" onclick="deleteRule('${chainName}', ${index})">Delete</button>
                </td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>
        <button class="btn btn-secondary" onclick="showAddRuleModal('${chainName}')">
            ➕ Add Rule to ${chainName}
        </button>
    `;

    return html;
}

function formatRuleOptions(rule) {
    const options = [];

    if (rule.interface) options.push(`iface: ${rule.interface}`);
    if (rule.sport) options.push(`sport: ${rule.sport}`);
    if (rule.dport) options.push(`dport: ${rule.dport}`);
    if (rule.state && rule.state.length > 0) options.push(`state: ${rule.state.join(',')}`);
    if (rule.comment) options.push(`comment: "${rule.comment}"`);
    if (rule.extra) options.push(rule.extra);

    return options.length > 0 ? options.join(' | ') : '-';
}

function toggleChain(chainName) {
    const content = document.getElementById(`chain-${chainName}`);
    const icon = content.previousElementSibling.querySelector('.toggle-icon');

    if (content.classList.contains('expanded')) {
        content.classList.remove('expanded');
        icon.textContent = '▼';
    } else {
        content.classList.add('expanded');
        icon.textContent = '▲';
    }
}

// Rule Form
function initializeRuleForm() {
    const form = document.getElementById('rule-form');
    const inputs = form.querySelectorAll('input, select, textarea');

    inputs.forEach(input => {
        input.addEventListener('input', updateRulePreview);
        input.addEventListener('change', updateRulePreview);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        saveRule();
    });
}

function updateRuleFormForType() {
    const chainSelect = document.getElementById('rule-chain');
    const protocolSelect = document.getElementById('rule-protocol');

    // Update available chains based on firewall type
    if (currentFirewallType === 'ebtables') {
        chainSelect.innerHTML = `
            <option value="INPUT">INPUT</option>
            <option value="OUTPUT">OUTPUT</option>
            <option value="FORWARD">FORWARD</option>
            <option value="BROUTING">BROUTING</option>
        `;

        protocolSelect.innerHTML = `
            <option value="">All</option>
            <option value="arp">ARP</option>
            <option value="ip">IP</option>
            <option value="ip6">IPv6</option>
        `;
    } else {
        chainSelect.innerHTML = `
            <option value="INPUT">INPUT</option>
            <option value="OUTPUT">OUTPUT</option>
            <option value="FORWARD">FORWARD</option>
            <option value="PREROUTING">PREROUTING</option>
            <option value="POSTROUTING">POSTROUTING</option>
        `;

        if (currentFirewallType === 'ip6tables') {
            protocolSelect.innerHTML = `
                <option value="">All</option>
                <option value="tcp">TCP</option>
                <option value="udp">UDP</option>
                <option value="icmpv6">ICMPv6</option>
                <option value="esp">ESP</option>
                <option value="ah">AH</option>
            `;
        } else {
            protocolSelect.innerHTML = `
                <option value="">All</option>
                <option value="tcp">TCP</option>
                <option value="udp">UDP</option>
                <option value="icmp">ICMP</option>
                <option value="esp">ESP</option>
                <option value="ah">AH</option>
            `;
        }
    }
}

function updateRulePreview() {
    const rule = getRuleFromForm();
    const command = generateIptablesCommand(rule);
    document.getElementById('rule-preview').textContent = command;
}

function getRuleFromForm() {
    const selectedStates = Array.from(document.getElementById('rule-state').selectedOptions)
        .map(opt => opt.value);

    return {
        chain: document.getElementById('rule-chain').value,
        target: document.getElementById('rule-target').value,
        protocol: document.getElementById('rule-protocol').value,
        interface: document.getElementById('rule-interface').value,
        source: document.getElementById('rule-source').value,
        destination: document.getElementById('rule-destination').value,
        sport: document.getElementById('rule-sport').value,
        dport: document.getElementById('rule-dport').value,
        state: selectedStates,
        comment: document.getElementById('rule-comment').value,
        extra: document.getElementById('rule-extra').value
    };
}

function generateIptablesCommand(rule) {
    const cmd = currentFirewallType;
    let parts = [cmd, '-A', rule.chain];

    if (rule.interface) {
        parts.push('-i', rule.interface);
    }

    if (rule.protocol) {
        parts.push('-p', rule.protocol);
    }

    if (rule.source) {
        parts.push('-s', rule.source);
    }

    if (rule.destination) {
        parts.push('-d', rule.destination);
    }

    if (rule.sport) {
        parts.push('--sport', rule.sport);
    }

    if (rule.dport) {
        parts.push('--dport', rule.dport);
    }

    if (rule.state && rule.state.length > 0) {
        parts.push('-m', 'state', '--state', rule.state.join(','));
    }

    if (rule.comment) {
        parts.push('-m', 'comment', '--comment', `"${rule.comment}"`);
    }

    if (rule.extra) {
        parts.push(rule.extra);
    }

    parts.push('-j', rule.target);

    return parts.join(' ');
}

function showAddRuleModal(chain = null) {
    document.getElementById('rule-modal-title').textContent = 'Add Firewall Rule';
    document.getElementById('rule-form').reset();

    if (chain) {
        document.getElementById('rule-chain').value = chain;
    }

    editingRuleIndex = null;
    editingChain = null;

    updateRulePreview();
    openModal('rule-modal');
}

function editRule(chain, index) {
    const rule = firewallConfig.chains[chain].rules[index];

    document.getElementById('rule-modal-title').textContent = 'Edit Firewall Rule';
    document.getElementById('rule-chain').value = rule.chain || chain;
    document.getElementById('rule-target').value = rule.target;
    document.getElementById('rule-protocol').value = rule.protocol || '';
    document.getElementById('rule-interface').value = rule.interface || '';
    document.getElementById('rule-source').value = rule.source || '';
    document.getElementById('rule-destination').value = rule.destination || '';
    document.getElementById('rule-sport').value = rule.sport || '';
    document.getElementById('rule-dport').value = rule.dport || '';
    document.getElementById('rule-comment').value = rule.comment || '';
    document.getElementById('rule-extra').value = rule.extra || '';

    // Set state multi-select
    const stateSelect = document.getElementById('rule-state');
    Array.from(stateSelect.options).forEach(opt => {
        opt.selected = rule.state && rule.state.includes(opt.value);
    });

    editingRuleIndex = index;
    editingChain = chain;

    updateRulePreview();
    openModal('rule-modal');
}

function saveRule() {
    const rule = getRuleFromForm();

    if (!firewallConfig.chains) {
        firewallConfig.chains = {};
    }

    if (!firewallConfig.chains[rule.chain]) {
        firewallConfig.chains[rule.chain] = {
            policy: 'ACCEPT',
            rules: []
        };
    }

    if (editingRuleIndex !== null && editingChain !== null) {
        // Update existing rule
        firewallConfig.chains[editingChain].rules[editingRuleIndex] = rule;
    } else {
        // Add new rule
        firewallConfig.chains[rule.chain].rules.push(rule);
    }

    renderFirewallRules();
    updateStatistics();
    closeModal('rule-modal');
    showAlert('Rule saved successfully', 'success');
}

function deleteRule(chain, index) {
    if (!confirm('Are you sure you want to delete this rule?')) return;

    firewallConfig.chains[chain].rules.splice(index, 1);

    // Remove chain if empty
    if (firewallConfig.chains[chain].rules.length === 0) {
        delete firewallConfig.chains[chain];
    }

    renderFirewallRules();
    updateStatistics();
    showAlert('Rule deleted successfully', 'success');
}

// Statistics
function updateStatistics() {
    let totalRules = 0;
    let acceptRules = 0;
    let dropRules = 0;
    let chainCount = 0;

    for (const [chainName, chainData] of Object.entries(firewallConfig.chains || {})) {
        chainCount++;
        if (chainData.rules) {
            totalRules += chainData.rules.length;

            chainData.rules.forEach(rule => {
                if (rule.target === 'ACCEPT') acceptRules++;
                if (rule.target === 'DROP' || rule.target === 'REJECT') dropRules++;
            });
        }
    }

    document.getElementById('stat-total-rules').textContent = totalRules;
    document.getElementById('stat-accept').textContent = acceptRules;
    document.getElementById('stat-drop').textContent = dropRules;
    document.getElementById('stat-chains').textContent = chainCount;
}

// Command Preview
function updateCommandPreview() {
    let commands = [];

    // Add flush commands
    commands.push(`# Flush existing rules`);
    commands.push(`${currentFirewallType} -F`);
    commands.push(``);

    // Add chain policies
    for (const [chainName, chainData] of Object.entries(firewallConfig.chains || {})) {
        if (chainData.policy) {
            commands.push(`${currentFirewallType} -P ${chainName} ${chainData.policy}`);
        }
    }

    commands.push(``);

    // Add rules
    for (const [chainName, chainData] of Object.entries(firewallConfig.chains || {})) {
        if (chainData.rules && chainData.rules.length > 0) {
            commands.push(`# ${chainName} chain rules`);
            chainData.rules.forEach(rule => {
                commands.push(generateIptablesCommand({ ...rule, chain: chainName }));
            });
            commands.push(``);
        }
    }

    document.getElementById('command-preview').textContent = commands.join('\n');
}

// Download Configuration
function downloadConfig() {
    updateCommandPreview();
    const content = document.getElementById('command-preview').textContent;

    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${currentFirewallType}-${Date.now()}.rules`;
    a.click();
    URL.revokeObjectURL(url);

    showAlert('Configuration downloaded', 'success');
}

// Save to Account
async function saveToAccount() {
    try {
        updateCommandPreview();
        const configData = document.getElementById('command-preview').textContent;

        const description = prompt('Enter a description for this version:');
        if (description === null) return; // User cancelled

        const response = await apiRequest('/api/v1/firewall/save', {
            method: 'POST',
            body: JSON.stringify({
                config_type: currentFirewallType,
                config_data: configData,
                device_name: firewallConfig.deviceName || 'Unnamed Device',
                description: description || 'No description'
            })
        });

        showAlert('Configuration saved to your account!', 'success');
    } catch (error) {
        showAlert(error.message, 'error');
    }
}

// Version History
async function showVersionHistory() {
    openModal('history-modal');

    try {
        const response = await apiRequest('/api/v1/firewall/list', {
            method: 'GET'
        });

        renderVersionHistory(response.data);
    } catch (error) {
        document.getElementById('history-content').innerHTML = `
            <div class="alert alert-error">${error.message}</div>
        `;
    }
}

function renderVersionHistory(versions) {
    if (!versions || versions.length === 0) {
        document.getElementById('history-content').innerHTML = `
            <div class="empty-state">
                <p>No saved versions yet</p>
            </div>
        `;
        return;
    }

    let html = '<div class="version-history">';

    versions.forEach((version, index) => {
        const date = new Date(version.created_at).toLocaleString();

        html += `
            <div class="version-item">
                <div>
                    <strong>Version ${version.version}</strong> - ${version.device_name}
                    <div style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">
                        ${version.description || 'No description'}
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.25rem;">
                        ${date} • ${version.config_type}
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-secondary" onclick="loadVersion('${version.id}')">Load</button>
                    <button class="btn btn-secondary" onclick="downloadVersion('${version.id}')">Download</button>
                    ${index > 0 ? `<button class="btn btn-secondary" onclick="showDiff('${versions[index - 1].id}', '${version.id}')">Diff</button>` : ''}
                </div>
            </div>
        `;
    });

    html += '</div>';
    document.getElementById('history-content').innerHTML = html;
}

async function loadVersion(versionId) {
    try {
        const response = await apiRequest(`/api/v1/firewall/get?id=${versionId}`, {
            method: 'GET'
        });

        // Parse the config data and load it
        firewallConfig = parseFirewallConfig(response.data.config_data, response.data.config_type);
        firewallConfig.deviceName = response.data.device_name;
        currentFirewallType = response.data.config_type;

        // Update UI
        document.querySelectorAll('.firewall-type-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.type === currentFirewallType);
        });

        renderFirewallRules();
        updateStatistics();
        closeModal('history-modal');
        showAlert('Version loaded successfully', 'success');
    } catch (error) {
        showAlert(error.message, 'error');
    }
}

async function downloadVersion(versionId) {
    try {
        const response = await apiRequest(`/api/v1/firewall/get?id=${versionId}`, {
            method: 'GET'
        });

        const blob = new Blob([response.data.config_data], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${response.data.config_type}-v${response.data.version}.rules`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (error) {
        showAlert(error.message, 'error');
    }
}

async function showDiff(oldVersionId, newVersionId) {
    try {
        const response = await apiRequest(`/api/v1/firewall/diff?old=${oldVersionId}&new=${newVersionId}`, {
            method: 'GET'
        });

        renderDiff(response.data);
        openModal('diff-modal');
    } catch (error) {
        showAlert(error.message, 'error');
    }
}

function renderDiff(diffData) {
    let html = '';

    diffData.diff.forEach(line => {
        const className = line.startsWith('+') ? 'diff-added' :
                         line.startsWith('-') ? 'diff-removed' : '';
        html += `<div class="${className}">${escapeHtml(line)}</div>`;
    });

    document.getElementById('diff-content').innerHTML = html;
}

// Utility Functions
function parseFirewallConfig(configText, type) {
    // This is a simplified parser - production would be more robust
    const config = {
        type: type,
        chains: {},
        rawConfig: configText
    };

    const lines = configText.split('\n');
    let currentChain = null;

    lines.forEach(line => {
        line = line.trim();

        // Skip comments and empty lines
        if (line.startsWith('#') || !line) return;

        // Parse chain policy
        if (line.includes(' -P ')) {
            const match = line.match(/-P\s+(\w+)\s+(\w+)/);
            if (match) {
                const [, chain, policy] = match;
                if (!config.chains[chain]) {
                    config.chains[chain] = { policy, rules: [] };
                } else {
                    config.chains[chain].policy = policy;
                }
            }
        }

        // Parse rule
        if (line.includes(' -A ')) {
            const match = line.match(/-A\s+(\w+)/);
            if (match) {
                currentChain = match[1];
                if (!config.chains[currentChain]) {
                    config.chains[currentChain] = { policy: 'ACCEPT', rules: [] };
                }

                const rule = parseRule(line);
                if (rule) {
                    config.chains[currentChain].rules.push(rule);
                }
            }
        }
    });

    return config;
}

function parseRule(line) {
    const rule = {
        target: '',
        protocol: '',
        source: '',
        destination: '',
        interface: '',
        sport: '',
        dport: '',
        state: [],
        comment: '',
        extra: ''
    };

    // Extract target
    const targetMatch = line.match(/-j\s+(\w+)/);
    if (targetMatch) rule.target = targetMatch[1];

    // Extract protocol
    const protoMatch = line.match(/-p\s+(\w+)/);
    if (protoMatch) rule.protocol = protoMatch[1];

    // Extract source
    const srcMatch = line.match(/-s\s+([\d\.\/:a-fA-F]+)/);
    if (srcMatch) rule.source = srcMatch[1];

    // Extract destination
    const dstMatch = line.match(/-d\s+([\d\.\/:a-fA-F]+)/);
    if (dstMatch) rule.destination = dstMatch[1];

    // Extract interface
    const ifaceMatch = line.match(/-i\s+(\w+)/);
    if (ifaceMatch) rule.interface = ifaceMatch[1];

    // Extract ports
    const sportMatch = line.match(/--sport\s+([\d:,]+)/);
    if (sportMatch) rule.sport = sportMatch[1];

    const dportMatch = line.match(/--dport\s+([\d:,]+)/);
    if (dportMatch) rule.dport = dportMatch[1];

    // Extract state
    const stateMatch = line.match(/--state\s+([\w,]+)/);
    if (stateMatch) rule.state = stateMatch[1].split(',');

    // Extract comment
    const commentMatch = line.match(/--comment\s+"([^"]+)"/);
    if (commentMatch) rule.comment = commentMatch[1];

    return rule;
}

function copyCommands() {
    const commands = document.getElementById('command-preview').textContent;
    navigator.clipboard.writeText(commands).then(() => {
        showAlert('Commands copied to clipboard!', 'success');
    });
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function showUploadModal() {
    openModal('upload-modal');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
