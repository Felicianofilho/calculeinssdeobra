/**
 * Sistema de Rastreamento de Usuários Online
 * Monitora atividade do usuário e atualiza status online
 */

class OnlineUsersTracker {
    constructor(options = {}) {
        // Configurações padrão
        this.config = {
            updateInterval: 30000, // 30 segundos
            activityTimeout: 60000, // 1 minuto
            endpoint: 'update_activity.php',
            debug: false,
            ...options
        };
        
        // Estado interno
        this.isOnline = true;
        this.lastActivity = Date.now();
        this.updateTimer = null;
        this.activityTimer = null;
        
        // Callbacks
        this.onCountUpdate = options.onCountUpdate || null;
        this.onError = options.onError || null;
        
        this.init();
    }
    
    /**
     * Inicializar o tracker
     */
    init() {
        this.log('Inicializando OnlineUsersTracker');
        
        // Registrar eventos de atividade
        this.registerActivityEvents();
        
        // Iniciar atualizações periódicas
        this.startPeriodicUpdates();
        
        // Atualizar imediatamente
        this.updateActivity();
        
        // Verificar se a página está visível
        this.setupVisibilityHandler();
    }
    
    /**
     * Registrar eventos que indicam atividade do usuário
     */
    registerActivityEvents() {
        const events = [
            'mousedown', 'mousemove', 'keypress', 'scroll',
            'touchstart', 'click', 'keydown', 'wheel'
        ];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.onUserActivity();
            }, { passive: true });
        });
        
        // Eventos específicos para formulários
        document.addEventListener('submit', () => this.onUserActivity());
        document.addEventListener('change', () => this.onUserActivity());
    }
    
    /**
     * Callback para atividade do usuário
     */
    onUserActivity() {
        this.lastActivity = Date.now();
        
        // Se estava offline, voltar online
        if (!this.isOnline) {
            this.isOnline = true;
            this.log('Usuário voltou online');
        }
        
        // Atualizar atividade após um pequeno delay para evitar spam
        clearTimeout(this.activityTimer);
        this.activityTimer = setTimeout(() => {
            this.updateActivity();
        }, 2000); // 2 segundos de delay
    }
    
    /**
     * Atualizar atividade no servidor
     */
    async updateActivity() {
        try {
            const response = await fetch(this.config.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.isOnline = true;
                
                // Chamar callback se definido
                if (this.onCountUpdate) {
                    this.onCountUpdate(data.online_count, data);
                }
                
                this.log(`Atividade atualizada. Usuários online: ${data.online_count}`);
            } else {
                throw new Error(data.message || 'Erro desconhecido');
            }
            
        } catch (error) {
            this.log(`Erro ao atualizar atividade: ${error.message}`, 'error');
            
            if (this.onError) {
                this.onError(error);
            }
        }
    }
    
    /**
     * Iniciar atualizações periódicas
     */
    startPeriodicUpdates() {
        this.updateTimer = setInterval(() => {
            // Verificar se houve atividade recente
            const timeSinceActivity = Date.now() - this.lastActivity;
            
            if (timeSinceActivity < this.config.activityTimeout) {
                this.updateActivity();
            } else {
                this.log('Usuário inativo, não atualizando');
            }
        }, this.config.updateInterval);
    }
    
    /**
     * Configurar handler para visibilidade da página
     */
    setupVisibilityHandler() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.log('Página oculta');
            } else {
                this.log('Página visível, atualizando atividade');
                this.updateActivity();
            }
        });
        
        // Atualizar quando a página ganha foco
        window.addEventListener('focus', () => {
            this.updateActivity();
        });
    }
    
    /**
     * Obter contagem atual de usuários online
     */
    async getCurrentCount() {
        try {
            const response = await fetch(this.config.endpoint, {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                return data.online_count || 0;
            }
        } catch (error) {
            this.log(`Erro ao obter contagem: ${error.message}`, 'error');
        }
        
        return 0;
    }
    
    /**
     * Parar o tracker
     */
    stop() {
        this.log('Parando OnlineUsersTracker');
        
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
            this.updateTimer = null;
        }
        
        if (this.activityTimer) {
            clearTimeout(this.activityTimer);
            this.activityTimer = null;
        }
    }
    
    /**
     * Log para debug
     */
    log(message, level = 'info') {
        if (this.config.debug) {
            const timestamp = new Date().toLocaleTimeString();
            console.log(`[${timestamp}] OnlineUsersTracker: ${message}`);
            
            if (level === 'error') {
                console.error(message);
            }
        }
    }
    
    /**
     * Destruir instância
     */
    destroy() {
        this.stop();
    }
}

// Auto-inicializar se não estiver em modo módulo
if (typeof module === 'undefined') {
    // Configurar tracker global
    window.OnlineUsersTracker = OnlineUsersTracker;
    
    // Auto-inicializar com configurações padrão
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.onlineTracker === 'undefined') {
            window.onlineTracker = new OnlineUsersTracker({
                debug: false, // Mude para true para debug
                onCountUpdate: function(count, data) {
                    // Atualizar contador na página se existir
                    const counterElement = document.getElementById('online-users-count');
                    if (counterElement) {
                        counterElement.textContent = count;
                    }
                    
                    // Disparar evento customizado
                    const event = new CustomEvent('onlineUsersUpdate', {
                        detail: { count, data }
                    });
                    document.dispatchEvent(event);
                }
            });
        }
    });
}


