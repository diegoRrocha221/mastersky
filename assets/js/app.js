class MicroERP {
    constructor() {
        this.baseUrl = '/api';
        this.currentUser = null;
        this.currentSection = 'dashboard';
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initComponents();
        this.checkAuth();
    }
    
    bindEvents() {
        // Login
        $('#loginForm').on('submit', (e) => this.handleLogin(e));
        
        // Logout
        $('#logout').on('click', (e) => this.handleLogout(e));
        
        // Navegação
        $('.nav-link[data-section]').on('click', (e) => this.handleNavigation(e));
        
        // Sidebar toggle
        $('#sidebarToggle').on('click', () => this.toggleSidebar());
        
        // Formulários modais
        $('.modal form').on('submit', (e) => this.handleModalForm(e));
        
        // Alternância de tipo pessoa
        $('input[name="tipoPessoa"]').on('change', (e) => this.toggleTipoPessoa(e));
        
        // Máscaras de input
        this.initMasks();
        
        // Validações em tempo real
        this.initValidations();
    }
    
    initComponents() {
        // Data atual
        this.updateCurrentDate();
        
        // Tooltips
        this.initTooltips();
        
        // Auto-save draft
        this.initAutoSave();
    }
    
    async checkAuth() {
        try {
            const response = await this.api('GET', '/auth/check');
            if (response.success) {
                this.currentUser = response.user;
                this.showMainSystem();
            } else {
                this.showLoginScreen();
            }
        } catch (error) {
            this.showLoginScreen();
        }
    }
    
    async handleLogin(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const credentials = {
            usuario: formData.get('usuario'),
            senha: formData.get('senha')
        };
        
        try {
            this.showLoading(true);
            const response = await this.api('POST', '/login', credentials);
            
            if (response.success) {
                this.currentUser = response.user;
                this.showMainSystem();
                this.loadDashboard();
                this.showAlert('success', `Bem-vindo, ${response.user.nome}!`);
            } else {
                this.showAlert('error', response.message || 'Erro ao fazer login');
            }
        } catch (error) {
            this.showAlert('error', 'Erro de conexão');
        } finally {
            this.showLoading(false);
        }
    }
    
    async handleLogout(e) {
        e.preventDefault();
        
        try {
            await this.api('POST', '/logout');
            this.currentUser = null;
            this.showLoginScreen();
            this.showAlert('info', 'Logout realizado com sucesso');
        } catch (error) {
            console.error('Erro no logout:', error);
        }
    }
    
    handleNavigation(e) {
        e.preventDefault();
        
        const section = $(e.target).data('section');
        this.navigateToSection(section);
    }
    
    navigateToSection(section) {
        // Atualizar estado
        this.currentSection = section;
        
        // Atualizar UI
        $('.nav-link').removeClass('active');
        $(`.nav-link[data-section="${section}"]`).addClass('active');
        
        // Esconder todas as seções
        $('.main-content').addClass('d-none');
        
        // Mostrar seção atual
        $(`#${section}-section`).removeClass('d-none').addClass('fade-in');
        
        // Carregar dados específicos da seção
        this.loadSectionData(section);
        
        // Fechar sidebar em mobile
        if (window.innerWidth <= 768) {
            this.closeSidebar();
        }
        
        // Atualizar URL (opcional)
        history.pushState({section}, '', `#${section}`);
    }
    
    async loadSectionData(section) {
        try {
            switch (section) {
                case 'dashboard':
                    await this.loadDashboard();
                    break;
                case 'colaboradores':
                    await this.loadColaboradores();
                    break;
                case 'produtos':
                    await this.loadProdutos();
                    break;
                case 'vendas':
                    await this.loadVendas();
                    break;
                case 'clientes':
                    await this.loadClientes();
                    break;
                case 'cargos':
                    await this.loadCargos();
                    break;
                case 'financeiro':
                    await this.loadFinanceiro();
                    break;
            }
        } catch (error) {
            console.error(`Erro ao carregar ${section}:`, error);
            this.showAlert('error', `Erro ao carregar dados de ${section}`);
        }
    }
    
    async loadDashboard() {
        try {
            const response = await this.api('GET', '/dashboard');
            if (response.success) {
                this.updateDashboardCards(response.data);
                this.updateDashboardCharts(response.data);
            }
        } catch (error) {
            console.error('Erro ao carregar dashboard:', error);
        }
    }
    
    async loadColaboradores() {
        try {
            const response = await this.api('GET', '/colaboradores');
            if (response.success) {
                this.updateColaboradoresTable(response.data);
            }
        } catch (error) {
            console.error('Erro ao carregar colaboradores:', error);
        }
    }
    
    async loadProdutos() {
        try {
            const response = await this.api('GET', '/produtos');
            if (response.success) {
                this.updateProdutosTable(response.data);
            }
        } catch (error) {
            console.error('Erro ao carregar produtos:', error);
        }
    }
    
    async loadVendas() {
        try {
            const response = await this.api('GET', '/vendas');
            if (response.success) {
                this.updateVendasTable(response.data);
            }
        } catch (error) {
            console.error('Erro ao carregar vendas:', error);
        }
    }
    
    updateDashboardCards(data) {
        // Atualizar cards com dados reais
        $('.stats-card').each((index, card) => {
            const $card = $(card);
            const tipo = $card.find('p').text().toLowerCase();
            
            if (tipo.includes('vendas')) {
                $card.find('h3').text(data.cards?.vendas || '0');
            } else if (tipo.includes('faturamento')) {
                $card.find('h3').text(this.formatCurrency(data.cards?.faturamento || 0));
            } else if (tipo.includes('colaboradores')) {
                $card.find('h3').text(data.cards?.colaboradores || '0');
            } else if (tipo.includes('estoque')) {
                $card.find('h3').text(data.cards?.estoque_baixo || '0');
            }
        });
    }
    
    updateDashboardCharts(data) {
        // Atualizar gráficos com dados reais do backend
        if (data.graficos?.vendas_por_mes) {
            this.updateVendasChart(data.graficos.vendas_por_mes);
        }
    }
    
    updateColaboradoresTable(data) {
        const tbody = $('#colaboradoresTable tbody');
        tbody.empty();
        
        data.forEach(colaborador => {
            const row = `
                <tr>
                    <td>${colaborador.id}</td>
                    <td>${colaborador.nome} ${colaborador.sobrenome}</td>
                    <td>${this.formatCPF(colaborador.cpf)}</td>
                    <td><span class="badge bg-primary">${colaborador.cargo_nome}</span></td>
                    <td>${colaborador.email || '-'}</td>
                    <td><span class="badge ${colaborador.ativo ? 'bg-success' : 'bg-danger'}">${colaborador.ativo ? 'Ativo' : 'Inativo'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="app.editColaborador(${colaborador.id})" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="app.deleteColaborador(${colaborador.id})" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
        
        // Reinicializar DataTable se necessário
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#colaboradoresTable')) {
            $('#colaboradoresTable').DataTable().destroy();
        }
        this.initDataTable('#colaboradoresTable');
    }
    
    async handleModalForm(e) {
        e.preventDefault();
        
        const $form = $(e.target);
        const $modal = $form.closest('.modal');
        const modalId = $modal.attr('id');
        
        try {
            this.showLoading(true);
            
            const formData = this.serializeForm($form);
            let endpoint = '';
            
            // Determinar endpoint baseado no modal
            if (modalId.includes('colaborador')) {
                endpoint = '/colaboradores';
            } else if (modalId.includes('produto')) {
                endpoint = '/produtos';
            } else if (modalId.includes('cliente')) {
                endpoint = '/clientes';
            } else if (modalId.includes('cargo')) {
                endpoint = '/cargos';
            } else if (modalId.includes('venda')) {
                endpoint = '/vendas';
            }
            
            const response = await this.api('POST', endpoint, formData);
            
            if (response.success) {
                $modal.modal('hide');
                $form[0].reset();
                this.showAlert('success', response.message);
                
                // Recarregar dados da seção atual
                this.loadSectionData(this.currentSection);
            } else {
                this.showAlert('error', response.message);
            }
        } catch (error) {
            this.showAlert('error', 'Erro ao salvar dados');
        } finally {
            this.showLoading(false);
        }
    }
    
    toggleTipoPessoa(e) {
        const tipo = $(e.target).val();
        
        if (tipo === 'fisica') {
            $('#dadosPF').show();
            $('#dadosPJ').hide();
        } else {
            $('#dadosPF').hide();
            $('#dadosPJ').show();
        }
    }
    
    toggleSidebar() {
        $('#sidebar').toggleClass('show');
    }
    
    closeSidebar() {
        $('#sidebar').removeClass('show');
    }
    
    showLoginScreen() {
        $('#mainSystem').addClass('d-none');
        $('#loginScreen').removeClass('d-none');
    }
    
    showMainSystem() {
        $('#loginScreen').addClass('d-none');
        $('#mainSystem').removeClass('d-none');
    }
    
    showLoading(show) {
        if (show) {
            $('body').addClass('loading');
        } else {
            $('body').removeClass('loading');
        }
    }
    
    showAlert(type, message) {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        $('body').append(alertHtml);
        
        // Remover após 5 segundos
        setTimeout(() => {
            $('.alert').fadeOut(() => {
                $('.alert').remove();
            });
        }, 5000);
    }
    
    updateCurrentDate() {
        const hoje = new Date();
        const opcoes = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            weekday: 'long'
        };
        $('#currentDate').text(hoje.toLocaleDateString('pt-BR', opcoes));
    }
    
    initMasks() {
        // Máscara CPF
        $('input[type="text"]').on('input', function() {
            const $this = $(this);
            const label = $this.prev('label').text().toLowerCase();
            let value = $this.val().replace(/\D/g, '');
            
            if (label.includes('cpf')) {
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                $this.val(value);
            } else if (label.includes('cnpj')) {
                value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
                $this.val(value);
            } else if (label.includes('telefone') || label.includes('celular')) {
                if (value.length === 11) {
                    value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                } else if (value.length === 10) {
                    value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                }
                $this.val(value);
            } else if (label.includes('cep')) {
                value = value.replace(/(\d{5})(\d{3})/, '$1-$2');
                $this.val(value);
            }
        });
        
        // Máscara para valores monetários
        $('input[type="number"][step="0.01"]').on('input', function() {
            let value = $(this).val();
            if (value && !isNaN(value)) {
                $(this).val(parseFloat(value).toFixed(2));
            }
        });
    }
    
    initValidations() {
        // Validação de CPF em tempo real
        $('input[placeholder*="CPF"], input[name*="cpf"]').on('blur', function() {
            const cpf = $(this).val().replace(/\D/g, '');
            if (cpf && !this.validateCPF(cpf)) {
                $(this).addClass('is-invalid');
                $(this).siblings('.invalid-feedback').remove();
                $(this).after('<div class="invalid-feedback">CPF inválido</div>');
            } else {
                $(this).removeClass('is-invalid');
                $(this).siblings('.invalid-feedback').remove();
            }
        }.bind(this));
        
        // Validação de CNPJ em tempo real
        $('input[placeholder*="CNPJ"], input[name*="cnpj"]').on('blur', function() {
            const cnpj = $(this).val().replace(/\D/g, '');
            if (cnpj && !this.validateCNPJ(cnpj)) {
                $(this).addClass('is-invalid');
                $(this).siblings('.invalid-feedback').remove();
                $(this).after('<div class="invalid-feedback">CNPJ inválido</div>');
            } else {
                $(this).removeClass('is-invalid');
                $(this).siblings('.invalid-feedback').remove();
            }
        }.bind(this));
        
        // Validação de email em tempo real
        $('input[type="email"]').on('blur', function() {
            const email = $(this).val();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                $(this).addClass('is-invalid');
                $(this).siblings('.invalid-feedback').remove();
                $(this).after('<div class="invalid-feedback">Email inválido</div>');
            } else {
                $(this).removeClass('is-invalid');
                $(this).siblings('.invalid-feedback').remove();
            }
        });
    }
    
    initTooltips() {
        // Inicializar tooltips do Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    initAutoSave() {
        // Auto-save para formulários longos
        let autoSaveTimer;
        
        $('.modal form input, .modal form textarea, .modal form select').on('input change', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                const formData = $(this).closest('form').serialize();
                localStorage.setItem('form_draft_' + $(this).closest('.modal').attr('id'), formData);
            }, 2000);
        });
        
        // Restaurar rascunhos
        $('.modal').on('show.bs.modal', function() {
            const draft = localStorage.getItem('form_draft_' + $(this).attr('id'));
            if (draft) {
                // Implementar restauração do rascunho se necessário
            }
        });
    }
    
    initDataTable(selector) {
        if ($.fn.DataTable) {
            $(selector).DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
                },
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']],
                columnDefs: [
                    { orderable: false, targets: -1 } // Última coluna (ações) não ordenável
                ]
            });
        }
    }
    
    async api(method, endpoint, data = null) {
        const url = this.baseUrl + endpoint;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }
    
    serializeForm($form) {
        const formData = {};
        
        $form.find('input, select, textarea').each(function() {
            const $input = $(this);
            const name = $input.attr('name');
            let value = $input.val();
            
            if (name) {
                if ($input.attr('type') === 'checkbox') {
                    value = $input.is(':checked');
                } else if ($input.attr('type') === 'number') {
                    value = parseFloat(value) || 0;
                }
                
                formData[name] = value;
            }
        });
        
        return formData;
    }
    
    validateCPF(cpf) {
        cpf = cpf.replace(/\D/g, '');
        
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
            return false;
        }
        
        let soma = 0;
        for (let i = 0; i < 9; i++) {
            soma += parseInt(cpf.charAt(i)) * (10 - i);
        }
        
        let resto = 11 - (soma % 11);
        let digito1 = resto < 2 ? 0 : resto;
        
        if (parseInt(cpf.charAt(9)) !== digito1) {
            return false;
        }
        
        soma = 0;
        for (let i = 0; i < 10; i++) {
            soma += parseInt(cpf.charAt(i)) * (11 - i);
        }
        
        resto = 11 - (soma % 11);
        let digito2 = resto < 2 ? 0 : resto;
        
        return parseInt(cpf.charAt(10)) === digito2;
    }
    
    validateCNPJ(cnpj) {
        cnpj = cnpj.replace(/\D/g, '');
        
        if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) {
            return false;
        }
        
        const weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        const weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        
        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(cnpj.charAt(i)) * weights1[i];
        }
        
        let digit1 = 11 - (sum % 11);
        digit1 = digit1 >= 10 ? 0 : digit1;
        
        if (parseInt(cnpj.charAt(12)) !== digit1) {
            return false;
        }
        
        sum = 0;
        for (let i = 0; i < 13; i++) {
            sum += parseInt(cnpj.charAt(i)) * weights2[i];
        }
        
        let digit2 = 11 - (sum % 11);
        digit2 = digit2 >= 10 ? 0 : digit2;
        
        return parseInt(cnpj.charAt(13)) === digit2;
    }
    
    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }
    
    formatCPF(cpf) {
        cpf = cpf.replace(/\D/g, '');
        return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }
    
    formatCNPJ(cnpj) {
        cnpj = cnpj.replace(/\D/g, '');
        return cnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    }
    
    formatPhone(phone) {
        phone = phone.replace(/\D/g, '');
        
        if (phone.length === 11) {
            return phone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (phone.length === 10) {
            return phone.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        }
        
        return phone;
    }
    
    // Métodos de CRUD específicos
    async editColaborador(id) {
        try {
            const response = await this.api('GET', `/colaboradores/${id}`);
            if (response.success) {
                this.populateForm('#colaboradorModal form', response.data);
                $('#colaboradorModal').modal('show');
            }
        } catch (error) {
            this.showAlert('error', 'Erro ao carregar dados do colaborador');
        }
    }
    
    async deleteColaborador(id) {
        if (confirm('Tem certeza que deseja excluir este colaborador?')) {
            try {
                const response = await this.api('DELETE', `/colaboradores/${id}`);
                if (response.success) {
                    this.showAlert('success', response.message);
                    this.loadColaboradores();
                } else {
                    this.showAlert('error', response.message);
                }
            } catch (error) {
                this.showAlert('error', 'Erro ao excluir colaborador');
            }
        }
    }
    
    populateForm(formSelector, data) {
        const $form = $(formSelector);
        
        Object.keys(data).forEach(key => {
            const $input = $form.find(`[name="${key}"]`);
            if ($input.length) {
                if ($input.attr('type') === 'checkbox') {
                    $input.prop('checked', data[key]);
                } else {
                    $input.val(data[key]);
                }
            }
        });
    }
}

// ====================================
// assets/js/charts.js - Gráficos
// ====================================

class ChartManager {
    constructor() {
        this.charts = {};
    }
    
    initVendasChart(data) {
        const ctx = document.getElementById('vendasChart');
        if (!ctx) return;
        
        // Destruir gráfico existente se houver
        if (this.charts.vendas) {
            this.charts.vendas.destroy();
        }
        
        this.charts.vendas = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(item => this.formatMonth(item.mes)),
                datasets: [{
                    label: 'Vendas',
                    data: data.map(item => item.total_vendas),
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                return `Vendas: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }
    
    initVendedoresChart(data) {
        const ctx = document.getElementById('vendedoresChart');
        if (!ctx) return;
        
        if (this.charts.vendedores) {
            this.charts.vendedores.destroy();
        }
        
        this.charts.vendedores = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(item => item.vendedor_nome),
                datasets: [{
                    data: data.map(item => item.valor_total),
                    backgroundColor: [
                        '#3498db',
                        '#e74c3c',
                        '#f39c12',
                        '#2ecc71',
                        '#9b59b6',
                        '#1abc9c'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = new Intl.NumberFormat('pt-BR', {
                                    style: 'currency',
                                    currency: 'BRL'
                                }).format(context.parsed);
                                return `${label}: ${value}`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    formatMonth(monthStr) {
        const [year, month] = monthStr.split('-');
        const months = [
            'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun',
            'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'
        ];
        return `${months[parseInt(month) - 1]}/${year.substr(-2)}`;
    }
}

// ====================================
// assets/js/utils.js - Utilitários
// ====================================

class Utils {
    static formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }
    
    static formatDate(date, format = 'dd/mm/yyyy') {
        if (!(date instanceof Date)) {
            date = new Date(date);
        }
        
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        
        switch (format) {
            case 'dd/mm/yyyy':
                return `${day}/${month}/${year}`;
            case 'yyyy-mm-dd':
                return `${year}-${month}-${day}`;
            case 'mm/dd/yyyy':
                return `${month}/${day}/${year}`;
            default:
                return date.toLocaleDateString('pt-BR');
        }
    }
    
    static debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }
    
    static throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
    
    static generateRandomString(length = 8) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }
    
    static copyToClipboard(text) {
        if (navigator.clipboard) {
            return navigator.clipboard.writeText(text);
        } else {
            // Fallback para navegadores mais antigos
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            return Promise.resolve(successful);
        }
    }
    
    static downloadJSON(data, filename = 'data.json') {
        const blob = new Blob([JSON.stringify(data, null, 2)], {
            type: 'application/json'
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
    
    static downloadCSV(data, filename = 'data.csv') {
        if (!data.length) return;
        
        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => 
                headers.map(header => 
                    JSON.stringify(row[header] || '')
                ).join(',')
            )
        ].join('\n');
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
    
    static printElement(selector) {
        const element = document.querySelector(selector);
        if (!element) return;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Impressão</title>
                    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        @media print {
                            body { font-size: 12px; }
                            .no-print { display: none !important; }
                        }
                    </style>
                </head>
                <body onload="window.print(); window.close();">
                    ${element.outerHTML}
                </body>
            </html>
        `);
        printWindow.document.close();
    }
}

// ====================================
// Inicialização da Aplicação
// ====================================

// Instâncias globais
let app;
let chartManager;

$(document).ready(function() {
    // Inicializar aplicação
    app = new MicroERP();
    chartManager = new ChartManager();
    
    // Configurações globais
    $.ajaxSetup({
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    // Event listeners globais
    $(window).on('resize', Utils.debounce(function() {
        // Redimensionar gráficos
        Object.values(chartManager.charts).forEach(chart => {
            if (chart) chart.resize();
        });
    }, 300));
    
    // Interceptar erros AJAX globais
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        if (xhr.status === 401) {
            app.showAlert('error', 'Sessão expirada. Faça login novamente.');
            app.showLoginScreen();
        } else if (xhr.status === 403) {
            app.showAlert('error', 'Acesso negado.');
        } else if (xhr.status === 500) {
            app.showAlert('error', 'Erro interno do servidor.');
        }
    });
    
    // Salvar estado da aplicação no localStorage
    window.addEventListener('beforeunload', function() {
        if (app.currentUser) {
            localStorage.setItem('lastSection', app.currentSection);
        }
    });
    
    // Restaurar estado da aplicação
    const lastSection = localStorage.getItem('lastSection');
    if (lastSection && app.currentUser) {
        app.navigateToSection(lastSection);
    }
});

// Funções globais para compatibilidade
function editColaborador(id) {
    app.editColaborador(id);
}

function deleteColaborador(id) {
    app.deleteColaborador(id);
}

function editProduto(id) {
    app.editProduto(id);
}

function deleteProduto(id) {
    app.deleteProduto(id);
}

// Exportar para módulos (se necessário)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { MicroERP, ChartManager, Utils };
}