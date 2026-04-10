// API Base URL
const API_BASE = '/splitwise_clone/api/';

// Global state
let currentUser = null;
let currentGroup = null;
let currentGroupMembers = [];

// Function for API calls
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include'
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    const response = await fetch(API_BASE + endpoint, options);
    return await response.json();
}

// Check if user is logged in
async function checkAuth() {
    const result = await apiCall('get_user.php');
    if (result.success) {
        currentUser = result.user;
        document.getElementById('user-name').textContent = currentUser.name;
        showDashboard();
        loadGroups();
    } else {
        showAuth();
    }
}

// UI Display Functions
function showAuth() {
    document.getElementById('auth-section').style.display = 'flex';
    document.getElementById('dashboard-section').style.display = 'none';
}

function showDashboard() {
    document.getElementById('auth-section').style.display = 'none';
    document.getElementById('dashboard-section').style.display = 'block';
}

// Load user's groups
async function loadGroups() {
    const result = await apiCall('get_groups.php');
    if (result.success) {
        const groupsList = document.getElementById('groups-list');
        if (result.groups.length === 0) {
            groupsList.innerHTML = '<p style="text-align: center; color: #999;">No groups yet. Create one!</p>';
        } else {
            groupsList.innerHTML = result.groups.map(group => `
                <div class="group-item" onclick="selectGroup(${group.id})">
                    <div class="group-name">${escapeHtml(group.group_name)}</div>
                    <div class="group-meta">${group.member_count} members</div>
                </div>
            `).join('');
        }
    }
}

// Select and load group details
async function selectGroup(groupId) {
    // Highlight selected group
    document.querySelectorAll('.group-item').forEach(item => {
        item.classList.remove('active');
    });
    event.target.closest('.group-item').classList.add('active');
    
    // Load group details
    const groupResult = await apiCall(`get_group.php?group_id=${groupId}`);
    if (!groupResult.success) {
        alert(groupResult.message);
        return;
    }
    
    currentGroup = groupResult.group;
    currentGroupMembers = groupResult.members;
    
    // Load balances
    const balancesResult = await apiCall(`get_balances.php?group_id=${groupId}`);
    const balances = balancesResult.success ? balancesResult.balances : [];
    
    // Load expenses
    const expensesResult = await apiCall(`get_expenses.php?group_id=${groupId}`);
    const expenses = expensesResult.success ? expensesResult.expenses : [];
    
    renderGroupView(currentGroup, currentGroupMembers, balances, expenses);
}

// Render group view
function renderGroupView(group, members, balances, expenses) {
    const mainContent = document.getElementById('group-view');
    
    const currentUserBalance = balances.find(b => b.user_id === currentUser.id);
    const currentUserNet = currentUserBalance ? currentUserBalance.balance : 0;
    
    mainContent.innerHTML = `
        <div class="group-header">
            <h2>${escapeHtml(group.group_name)}</h2>
            <div class="group-actions">
                <button class="btn-secondary" onclick="showAddMemberModal()">+ Add Member</button>
                <button class="btn-primary" onclick="showAddExpenseModal()">+ Add Expense</button>
            </div>
        </div>
        
        <div class="balances-section">
            <div class="section-title">Balances</div>
            <div class="balance-list">
                ${balances.map(b => {
                    const balanceClass = b.balance > 0 ? 'balance-positive' : (b.balance < 0 ? 'balance-negative' : 'balance-zero');
                    const balanceText = b.balance > 0 ? `gets back $${b.balance.toFixed(2)}` : (b.balance < 0 ? `owes $${Math.abs(b.balance).toFixed(2)}` : 'settled up');
                    const showSettle = b.balance < 0 && b.user_id !== currentUser.id;
                    return `
                        <div class="balance-item">
                            <span><strong>${escapeHtml(b.name)}</strong></span>
                            <div>
                                <span class="${balanceClass}">${balanceText}</span>
                                ${showSettle ? `<button class="settle-btn" onclick="showSettleModal(${b.user_id}, '${escapeHtml(b.name)}', ${Math.abs(b.balance)})">Settle</button>` : ''}
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
            <div style="margin-top: 10px; padding: 10px; background: #f0f0f0; border-radius: 8px;">
                <strong>Your net balance:</strong> 
                <span class="${currentUserNet > 0 ? 'balance-positive' : (currentUserNet < 0 ? 'balance-negative' : 'balance-zero')}">
                    ${currentUserNet > 0 ? `You are owed $${currentUserNet.toFixed(2)}` : (currentUserNet < 0 ? `You owe $${Math.abs(currentUserNet).toFixed(2)}` : 'You are settled up')}
                </span>
            </div>
        </div>
        
        <div class="expenses-section">
            <div class="section-title">Recent Expenses</div>
            ${expenses.length === 0 ? '<p style="color: #999;">No expenses yet. Add one!</p>' : 
                expenses.map(expense => `
                    <div class="expense-item">
                        <div class="expense-header">
                            <span class="expense-desc">${escapeHtml(expense.description)}</span>
                            <span class="expense-amount">$${parseFloat(expense.amount).toFixed(2)}</span>
                        </div>
                        <div class="expense-details">
                            Paid by ${escapeHtml(expense.paid_by_name)} on ${expense.expense_date}<br>
                            Split: ${expense.splits.map(s => `${escapeHtml(s.name)} ($${parseFloat(s.share).toFixed(2)})`).join(', ')}
                        </div>
                    </div>
                `).join('')
            }
        </div>
        
        <div class="members-section">
            <div class="section-title">Members (${members.length})</div>
            <div class="member-list">
                ${members.map(m => `<div class="member-badge">${escapeHtml(m.name)}</div>`).join('')}
            </div>
        </div>
    `;
}

// Modal Functions
function showCreateGroupModal() {
    document.getElementById('create-group-modal').style.display = 'block';
}

function showAddExpenseModal() {
    if (!currentGroup) {
        alert('Please select a group first');
        return;
    }
    
    const paidBySelect = document.getElementById('expense-paid-by');
    paidBySelect.innerHTML = currentGroupMembers.map(m => 
        `<option value="${m.id}" ${m.id === currentUser.id ? 'selected' : ''}>${escapeHtml(m.name)}</option>`
    ).join('');
    
    document.getElementById('expense-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('expense-modal').style.display = 'block';
}

function showAddMemberModal() {
    if (!currentGroup) {
        alert('Please select a group first');
        return;
    }
    document.getElementById('member-modal').style.display = 'block';
}

function showSettleModal(toUserId, toUserName, suggestedAmount) {
    document.getElementById('settle-to-name').textContent = toUserName;
    document.getElementById('settle-amount').value = suggestedAmount.toFixed(2);
    document.getElementById('settle-modal').dataset.toUserId = toUserId;
    document.getElementById('settle-modal').style.display = 'block';
}

// Event Handlers
// document.getElementById('login-btn').addEventListener('click', async () => {
//     const email = document.getElementById('login-email').value;
//     const password = document.getElementById('login-password').value;
    
//     const result = await apiCall('login.php', 'POST', { email, password });
//     if (result.success) {
//         currentUser = result.user;
//         document.getElementById('user-name').textContent = currentUser.name;
//         showDashboard();
//         loadGroups();
//     } else {
//         document.getElementById('login-error').textContent = result.message;
//     }
// });

// document.getElementById('register-btn').addEventListener('click', async () => {
//     const name = document.getElementById('register-name').value;
//     const email = document.getElementById('register-email').value;
//     const password = document.getElementById('register-password').value;
    
//     const result = await apiCall('register.php', 'POST', { name, email, password });
//     if (result.success) {
//         alert('Registration successful! Please login.');
//         document.querySelector('[data-tab="login"]').click();
//         document.getElementById('register-error').textContent = '';
//     } else {
//         document.getElementById('register-error').textContent = result.message;
//     }
// });

const loginBtn = document.getElementById('login-btn');
if (loginBtn) {
    loginBtn.addEventListener('click', async () => {
        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;

        const result = await apiCall('login.php', 'POST', { email, password });
        if (result.success) {
            currentUser = result.user;
            document.getElementById('user-name').textContent = currentUser.name;
            showDashboard();
            loadGroups();
        } else {
            document.getElementById('login-error').textContent = result.message;
        }
    });
}

const registerBtn = document.getElementById('register-btn');
if (registerBtn) {
    registerBtn.addEventListener('click', async () => {
        const name = document.getElementById('register-name').value;
        const email = document.getElementById('register-email').value;
        const password = document.getElementById('register-password').value;

        const result = await apiCall('register.php', 'POST', { name, email, password });

        if (result.success) {
            alert('Registration successful! Please login.');
            document.querySelector('[data-tab="login"]').click();
            document.getElementById('register-error').textContent = '';
        } else {
            document.getElementById('register-error').textContent = result.message;
        }
    });
}

document.getElementById('logout-btn').addEventListener('click', async () => {
    await apiCall('logout.php');
    currentUser = null;
    currentGroup = null;
    showAuth();
});

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
        document.getElementById(`${tab}-form`).classList.add('active');
    });
});

// Create Group
document.getElementById('create-group-btn').addEventListener('click', showCreateGroupModal);
document.getElementById('confirm-create-group').addEventListener('click', async () => {
    const groupName = document.getElementById('new-group-name').value;
    if (!groupName) {
        alert('Please enter a group name');
        return;
    }
    
    const result = await apiCall('create_group.php', 'POST', { group_name: groupName });
    if (result.success) {
        document.getElementById('create-group-modal').style.display = 'none';
        document.getElementById('new-group-name').value = '';
        loadGroups();
    } else {
        alert(result.message);
    }
});

// Add Expense
document.getElementById('confirm-expense').addEventListener('click', async () => {
    const description = document.getElementById('expense-desc').value;
    const amount = document.getElementById('expense-amount').value;
    const paidBy = document.getElementById('expense-paid-by').value;
    const expenseDate = document.getElementById('expense-date').value;
    
    if (!description || !amount || !expenseDate) {
        alert('Please fill all fields');
        return;
    }
    
    const result = await apiCall('add_expense.php', 'POST', {
        group_id: currentGroup.id,
        description,
        amount,
        paid_by: paidBy,
        expense_date: expenseDate
    });
    
    if (result.success) {
        document.getElementById('expense-modal').style.display = 'none';
        document.getElementById('expense-desc').value = '';
        document.getElementById('expense-amount').value = '';
        selectGroup(currentGroup.id);
    } else {
        alert(result.message);
    }
});

// Add Member
document.getElementById('confirm-member').addEventListener('click', async () => {
    const email = document.getElementById('member-email').value;
    if (!email) {
        alert('Please enter email');
        return;
    }
    
    const result = await apiCall('add_member.php', 'POST', {
        group_id: currentGroup.id,
        email
    });
    
    if (result.success) {
        document.getElementById('member-modal').style.display = 'none';
        document.getElementById('member-email').value = '';
        selectGroup(currentGroup.id);
    } else {
        alert(result.message);
    }
});

// Settle
document.getElementById('confirm-settle').addEventListener('click', async () => {
    const toUserId = document.getElementById('settle-modal').dataset.toUserId;
    const amount = document.getElementById('settle-amount').value;
    
    if (!amount || amount <= 0) {
        alert('Please enter a valid amount');
        return;
    }
    
    const result = await apiCall('settle.php', 'POST', {
        group_id: currentGroup.id,
        to_user_id: toUserId,
        amount
    });
    
    if (result.success) {
        document.getElementById('settle-modal').style.display = 'none';
        selectGroup(currentGroup.id);
    } else {
        alert(result.message);
    }
});

// Close modals
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', () => {
        closeBtn.closest('.modal').style.display = 'none';
    });
});

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize
checkAuth();