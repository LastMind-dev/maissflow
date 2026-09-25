import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import axios from 'axios';
import {
    ReactFlow,
    Background,
    Controls,
    MiniMap,
    Handle,
    Position,
    addEdge,
    useEdgesState,
    useNodesState,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { connectionDestination, resolveConnectionEdge } from './flow-navigation';
import {
    Activity,
    ArrowLeft,
    ArrowRight,
    Bell,
    Bot,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    CircleHelp,
    Clock3,
    Copy,
    ExternalLink,
    GitBranch,
    Inbox,
    KeyRound,
    LayoutDashboard,
    ListTree,
    LoaderCircle,
    LockKeyhole,
    LogOut,
    Menu,
    MessageCircle,
    MessageSquare,
    MoreHorizontal,
    MousePointer2,
    PanelRightClose,
    Phone,
    Play,
    Plus,
    RefreshCw,
    Rocket,
    Search,
    Send,
    Settings,
    ShieldCheck,
    Sparkles,
    Timer,
    Trash2,
    UserCheck,
    UserRound,
    Users,
    Wifi,
    WifiOff,
    Workflow,
    X,
    Zap,
} from 'lucide-react';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common.Accept = 'application/json';
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrf) axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;

const api = axios.create({ baseURL: '/api/app' });

const formatDate = (value, options = {}) => value
    ? new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        ...options,
    }).format(new Date(value))
    : '—';

const errorMessage = (error) => {
    const errors = error?.response?.data?.errors;
    if (errors) return Object.values(errors).flat().join(' ');
    return error?.response?.data?.message || 'Não foi possível concluir a operação.';
};

function routeFromLocation() {
    const path = window.location.pathname.replace(/^\/app\/?/, '');
    const [page = 'dashboard', id] = path.split('/');
    return { page: page || 'dashboard', id: id || null };
}

function App() {
    const [route, setRoute] = useState(routeFromLocation);
    const [bootstrap, setBootstrap] = useState(null);
    const [loading, setLoading] = useState(true);
    const [toast, setToast] = useState(null);
    const [mobileNav, setMobileNav] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);

    const notify = useCallback((message, type = 'success') => {
        setToast({ message, type });
        window.setTimeout(() => setToast(null), 4200);
    }, []);

    const loadBootstrap = useCallback(async () => {
        const { data } = await api.get('/bootstrap');
        setBootstrap(data);
    }, []);

    useEffect(() => {
        loadBootstrap()
            .catch((error) => notify(errorMessage(error), 'error'))
            .finally(() => setLoading(false));
    }, [loadBootstrap, notify]);

    useEffect(() => {
        const handlePop = () => setRoute(routeFromLocation());
        window.addEventListener('popstate', handlePop);
        return () => window.removeEventListener('popstate', handlePop);
    }, []);

    const navigate = useCallback((page, id = null) => {
        const url = `/app/${page === 'dashboard' ? '' : page}${id ? `/${id}` : ''}`;
        window.history.pushState({}, '', url);
        setRoute({ page, id });
        setMobileNav(false);
    }, []);

    if (loading) {
        return (
            <div className="app-loading">
                <div className="loading-mark">F</div>
                <LoaderCircle className="spin" size={22} />
                <p>Preparando seu workspace…</p>
            </div>
        );
    }

    return (
        <div className="app-shell">
            <Sidebar
                route={route}
                navigate={navigate}
                bootstrap={bootstrap}
                notify={notify}
                open={mobileNav}
                onClose={() => setMobileNav(false)}
            />
            <div className="app-main">
                <Topbar
                    bootstrap={bootstrap}
                    onMenu={() => setMobileNav(true)}
                    onHelp={() => navigate('settings')}
                    onNotifications={() => navigate('inbox')}
                    onProfile={() => setProfileOpen(true)}
                />
                <main className={`page-content ${route.page === 'automations' && route.id ? 'builder-page-content' : ''}`}>
                    {route.page === 'dashboard' && (
                        <Dashboard bootstrap={bootstrap} navigate={navigate} />
                    )}
                    {route.page === 'automations' && !route.id && (
                        <AutomationsPage navigate={navigate} notify={notify} />
                    )}
                    {route.page === 'automations' && route.id && (
                        <FlowBuilder automationId={route.id} navigate={navigate} notify={notify} flowActions={bootstrap?.flow_actions || []} />
                    )}
                    {route.page === 'inbox' && (
                        <InboxPage initialId={route.id} notify={notify} />
                    )}
                    {route.page === 'contacts' && <ContactsPage notify={notify} />}
                    {route.page === 'settings' && (
                        <SettingsPage
                            channel={bootstrap?.channel}
                            onUpdated={loadBootstrap}
                            notify={notify}
                        />
                    )}
                </main>
            </div>
            {toast && (
                <div className={`toast toast-${toast.type}`}>
                    {toast.type === 'success' ? <CheckCircle2 size={19} /> : <CircleHelp size={19} />}
                    <span>{toast.message}</span>
                    <button onClick={() => setToast(null)} aria-label="Fechar"><X size={16} /></button>
                </div>
            )}
            {profileOpen && (
                <ProfileModal
                    user={bootstrap?.user}
                    onClose={() => setProfileOpen(false)}
                    onUpdated={loadBootstrap}
                    notify={notify}
                />
            )}
        </div>
    );
}

function Sidebar({ route, navigate, bootstrap, notify, open, onClose }) {
    const items = [
        { id: 'dashboard', label: 'Visão geral', icon: LayoutDashboard },
        { id: 'contacts', label: 'Contatos', icon: Users },
        { id: 'automations', label: 'Automações', icon: Workflow },
        { id: 'inbox', label: 'Caixa de entrada', icon: MessageSquare, badge: bootstrap?.metrics?.unread || null },
        { id: 'settings', label: 'Configurações', icon: Settings },
    ];

    return (
        <>
            {open && <button className="mobile-overlay" onClick={onClose} aria-label="Fechar menu" />}
            <aside className={`sidebar ${open ? 'sidebar-open' : ''}`}>
                <div className="sidebar-logo">
                    <div className="logo-mark">F</div>
                    <div><strong>MaissFlow</strong><span>WhatsApp Suite</span></div>
                    <button className="mobile-close" aria-label="Fechar menu" onClick={onClose}><X size={18} /></button>
                </div>
                <button className="workspace-switcher" onClick={() => notify('Este ambiente possui um único workspace corporativo.', 'info')}>
                    <div className="workspace-avatar">{bootstrap?.workspace?.name?.slice(0, 1) || 'S'}</div>
                    <div><strong>{bootstrap?.workspace?.name || 'Sua empresa'}</strong><span>Workspace corporativo</span></div>
                    <ChevronDown size={15} />
                </button>
                <nav className="primary-nav">
                    {items.map(({ id, label, icon: Icon, badge }) => (
                        <button
                            key={id}
                            className={route.page === id ? 'active' : ''}
                            onClick={() => navigate(id)}
                        >
                            <Icon size={19} strokeWidth={1.8} />
                            <span>{label}</span>
                            {badge ? <em>{badge > 99 ? '99+' : badge}</em> : null}
                        </button>
                    ))}
                </nav>
                <div className="sidebar-spacer" />
                <div className="sidebar-help">
                    <div><Sparkles size={17} /><strong>Central de ajuda</strong></div>
                    <p>Configure a Meta e publique seu primeiro fluxo.</p>
                    <button onClick={() => navigate('settings')}>Ver configuração <ArrowRight size={14} /></button>
                </div>
                <div className="sidebar-footer">
                    <div className="user-avatar">{bootstrap?.user?.name?.slice(0, 1) || 'U'}</div>
                    <div><strong>{bootstrap?.user?.name}</strong><span>{bootstrap?.user?.role}</span></div>
                    <form action="/logout" method="POST">
                        <input type="hidden" name="_token" value={csrf || ''} />
                        <button type="submit" title="Sair"><LogOut size={17} /></button>
                    </form>
                </div>
            </aside>
        </>
    );
}

function Topbar({ bootstrap, onMenu, onHelp, onNotifications, onProfile }) {
    const connected = bootstrap?.channel?.status === 'connected';
    return (
        <header className="topbar">
            <button className="mobile-menu" aria-label="Abrir menu principal" onClick={onMenu}><Menu size={21} /></button>
            <div className={`connection-pill ${connected ? 'connected' : ''}`}>
                {connected ? <Wifi size={15} /> : <WifiOff size={15} />}
                <span>{connected ? 'WhatsApp conectado' : 'WhatsApp aguardando configuração'}</span>
            </div>
            <div className="topbar-actions">
                <button title="Ajuda" onClick={onHelp}><CircleHelp size={19} /></button>
                <button title="Notificações" className="notification-button" onClick={onNotifications}><Bell size={19} />{bootstrap?.metrics?.unread > 0 && <i />}</button>
                <button className="top-avatar" title="Meu perfil" aria-label="Abrir meu perfil" onClick={onProfile}>
                    {bootstrap?.user?.name?.slice(0, 1)}
                </button>
            </div>
        </header>
    );
}

function Dashboard({ bootstrap, navigate }) {
    const [automations, setAutomations] = useState([]);
    useEffect(() => {
        api.get('/automations').then(({ data }) => setAutomations(data.data)).catch(() => {});
    }, []);
    const metrics = bootstrap?.metrics || {};
    const cards = [
        { label: 'Conversas abertas', value: metrics.open_conversations || 0, detail: `${metrics.unread || 0} não lidas`, icon: MessageCircle, color: 'green' },
        { label: 'Contatos', value: metrics.contacts || 0, detail: 'base própria', icon: Users, color: 'blue' },
        { label: 'Mensagens hoje', value: metrics.messages_today || 0, detail: 'entrada e saída', icon: Send, color: 'violet' },
        { label: 'Automações ativas', value: metrics.active_automations || 0, detail: `${metrics.automations || 0} no total`, icon: Zap, color: 'orange' },
    ];
    return (
        <div className="standard-page">
            <PageHeader
                eyebrow="VISÃO GERAL"
                title={`Olá, ${bootstrap?.user?.name?.split(' ')[0] || 'gestor'}!`}
                description="Acompanhe a operação do seu atendimento em tempo real."
                actions={<button className="primary-button" onClick={() => navigate('automations')}><Plus size={17} /> Nova automação</button>}
            />

            {!bootstrap?.channel?.configured && (
                <div className="setup-banner">
                    <div className="setup-icon"><Phone size={22} /></div>
                    <div>
                        <strong>Finalize a conexão com a Meta</strong>
                        <p>Cadastre o Phone Number ID, WABA, token permanente e os dados do webhook.</p>
                    </div>
                    <button onClick={() => navigate('settings')}>Configurar agora <ArrowRight size={16} /></button>
                </div>
            )}

            <section className="metric-grid">
                {cards.map(({ label, value, detail, icon: Icon, color }) => (
                    <article className="metric-card" key={label}>
                        <div className={`metric-icon ${color}`}><Icon size={20} /></div>
                        <div className="metric-value">{value.toLocaleString('pt-BR')}</div>
                        <strong>{label}</strong>
                        <span>{detail}</span>
                    </article>
                ))}
            </section>

            <section className="dashboard-grid">
                <article className="panel automations-overview">
                    <div className="panel-heading">
                        <div><h3>Automações</h3><p>Fluxos recentes e status de publicação</p></div>
                        <button className="text-button" onClick={() => navigate('automations')}>Ver todas <ArrowRight size={15} /></button>
                    </div>
                    {automations.length ? (
                        <div className="automation-mini-list">
                            {automations.slice(0, 4).map((automation) => (
                                <button key={automation.public_id} onClick={() => navigate('automations', automation.public_id)}>
                                    <div className={`automation-list-icon ${automation.status}`}><Workflow size={18} /></div>
                                    <div><strong>{automation.name}</strong><span>Atualizado {formatDate(automation.updated_at)}</span></div>
                                    <StatusBadge status={automation.status} />
                                    <ChevronRight size={17} />
                                </button>
                            ))}
                        </div>
                    ) : <EmptyState icon={Workflow} title="Nenhuma automação" text="Crie um fluxo para iniciar o atendimento." />}
                </article>

                <article className="panel activity-panel">
                    <div className="panel-heading"><div><h3>Atendimentos recentes</h3><p>Últimas conversas recebidas</p></div></div>
                    {bootstrap?.recent_conversations?.length ? (
                        <div className="recent-list">
                            {bootstrap.recent_conversations.map((conversation) => (
                                <button key={conversation.public_id} onClick={() => navigate('inbox', conversation.public_id)}>
                                    <div className="contact-avatar">{conversation.contact?.name?.slice(0, 1) || '?'}</div>
                                    <div><strong>{conversation.contact?.name || conversation.contact?.phone_number}</strong><span>{formatDate(conversation.last_message_at)}</span></div>
                                    {conversation.unread_count > 0 && <em>{conversation.unread_count}</em>}
                                </button>
                            ))}
                        </div>
                    ) : <EmptyState icon={Inbox} title="Caixa tranquila" text="Novas conversas aparecerão aqui." />}
                </article>
            </section>
        </div>
    );
}

function PageHeader({ eyebrow, title, description, actions }) {
    return (
        <header className="page-header">
            <div><p className="eyebrow">{eyebrow}</p><h1>{title}</h1><span>{description}</span></div>
            {actions && <div className="page-actions">{actions}</div>}
        </header>
    );
}

function StatusBadge({ status }) {
    const labels = {
        active: 'Publicado',
        draft: 'Rascunho',
        bot: 'Automação',
        human: 'Humano',
        closed: 'Encerrado',
        connected: 'Conectado',
        configured: 'Configurado',
        disconnected: 'Desconectado',
        error: 'Com erro',
    };
    return <span className={`status-badge status-${status}`}><i />{labels[status] || status}</span>;
}

function EmptyState({ icon: Icon, title, text }) {
    return <div className="empty-state"><div><Icon size={24} /></div><strong>{title}</strong><p>{text}</p></div>;
}

function AutomationsPage({ navigate, notify }) {
    const [automations, setAutomations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modal, setModal] = useState(false);
    const [name, setName] = useState('Atendimento principal');
    const [blank, setBlank] = useState(false);
    const [creating, setCreating] = useState(false);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const filteredAutomations = useMemo(() => automations.filter((automation) => {
        const matchesSearch = automation.name.toLocaleLowerCase('pt-BR')
            .includes(search.trim().toLocaleLowerCase('pt-BR'));
        const matchesStatus = statusFilter === 'all' || automation.status === statusFilter;
        return matchesSearch && matchesStatus;
    }), [automations, search, statusFilter]);

    const load = useCallback(() => {
        setLoading(true);
        api.get('/automations')
            .then(({ data }) => setAutomations(data.data))
            .catch((error) => notify(errorMessage(error), 'error'))
            .finally(() => setLoading(false));
    }, [notify]);
    useEffect(load, [load]);

    const create = async (event) => {
        event.preventDefault();
        setCreating(true);
        try {
            const { data } = await api.post('/automations', { name, blank });
            notify('Automação criada. O rascunho está pronto para edição.');
            navigate('automations', data.data.public_id);
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setCreating(false);
        }
    };

    const duplicate = async (automation) => {
        try {
            await api.post(`/automations/${automation.public_id}/duplicate`);
            notify('Automação duplicada com sucesso.');
            load();
        } catch (error) {
            notify(errorMessage(error), 'error');
        }
    };

    return (
        <div className="standard-page">
            <PageHeader
                eyebrow="AUTOMAÇÕES"
                title="Fluxos de atendimento"
                description="Crie jornadas visuais, publique versões seguras e acompanhe alterações."
                actions={<button className="primary-button" onClick={() => setModal(true)}><Plus size={17} /> Nova automação</button>}
            />
            <div className="filter-row">
                <div className="search-field"><Search size={17} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar automação…" /></div>
                <label className="select-filter">
                    <span className="sr-only">Filtrar por status</span>
                    <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
                        <option value="all">Todos os status</option>
                        <option value="active">Publicados</option>
                        <option value="draft">Rascunhos</option>
                    </select>
                    <ChevronDown size={15} />
                </label>
            </div>
            {loading ? <PageLoader /> : (
                <div className="automation-card-grid">
                    {filteredAutomations.map((automation) => (
                        <article className="automation-card" key={automation.public_id}>
                            <div className="automation-card-top">
                                <div className={`automation-icon ${automation.status}`}><Workflow size={22} /></div>
                                <StatusBadge status={automation.status} />
                                <button className="icon-button" title="Abrir editor" onClick={() => navigate('automations', automation.public_id)}><MoreHorizontal size={19} /></button>
                            </div>
                            <h3>{automation.name}</h3>
                            <p>{automation.description || 'Fluxo visual de atendimento pelo WhatsApp.'}</p>
                            <div className="automation-meta">
                                <span><ListTree size={15} /> v{automation.draft_revision || 1} do rascunho</span>
                                <span><Clock3 size={15} /> {formatDate(automation.updated_at)}</span>
                            </div>
                            <div className="automation-card-footer">
                                <button className="secondary-button compact" onClick={() => duplicate(automation)}><Copy size={15} /> Duplicar</button>
                                <button className="primary-button compact" onClick={() => navigate('automations', automation.public_id)}>Abrir editor <ArrowRight size={15} /></button>
                            </div>
                        </article>
                    ))}
                    {!automations.length && (
                        <button className="new-automation-card" onClick={() => setModal(true)}>
                            <div><Plus size={24} /></div><strong>Criar primeira automação</strong><span>Comece com o menu de exemplo ou com uma tela em branco.</span>
                        </button>
                    )}
                    {automations.length > 0 && !filteredAutomations.length && (
                        <div className="panel filtered-empty"><EmptyState icon={Search} title="Nenhum fluxo encontrado" text="Ajuste a busca ou o filtro de status." /></div>
                    )}
                </div>
            )}
            {modal && (
                <Modal title="Nova automação" onClose={() => setModal(false)}>
                    <form className="modal-form" onSubmit={create}>
                        <label>Nome da automação</label>
                        <input value={name} onChange={(event) => setName(event.target.value)} autoFocus maxLength={120} required />
                        <div className="template-choice">
                            <button type="button" className={!blank ? 'selected' : ''} onClick={() => setBlank(false)}>
                                <Sparkles size={20} /><strong>Menu completo</strong><span>Fluxo inspirado na estrutura enviada</span>
                            </button>
                            <button type="button" className={blank ? 'selected' : ''} onClick={() => setBlank(true)}>
                                <MousePointer2 size={20} /><strong>Em branco</strong><span>Apenas o gatilho inicial</span>
                            </button>
                        </div>
                        <div className="modal-actions">
                            <button type="button" className="secondary-button" onClick={() => setModal(false)}>Cancelar</button>
                            <button className="primary-button" disabled={creating}>{creating && <LoaderCircle className="spin" size={16} />} Criar automação</button>
                        </div>
                    </form>
                </Modal>
            )}
        </div>
    );
}

const nodeMeta = {
    trigger: { label: 'Quando…', icon: Zap, className: 'trigger' },
    message: { label: 'Enviar mensagem', icon: MessageCircle, className: 'message' },
    menu: { label: 'Menu interativo', icon: ListTree, className: 'menu' },
    input: { label: 'Coletar resposta', icon: MessageSquare, className: 'input' },
    condition: { label: 'Condição', icon: GitBranch, className: 'condition' },
    action: { label: 'Executar ação', icon: Activity, className: 'action' },
    delay: { label: 'Aguardar', icon: Timer, className: 'delay' },
    handoff: { label: 'Atendimento humano', icon: UserCheck, className: 'handoff' },
    end: { label: 'Finalizar', icon: CheckCircle2, className: 'end' },
};

const FlowNavigationContext = React.createContext(null);

function FlowNode({ id, data, type, selected }) {
    const meta = nodeMeta[type] || nodeMeta.message;
    const Icon = meta.icon;
    const navigateConnection = React.useContext(FlowNavigationContext);
    const pointerStartRef = useRef(null);
    const options = data.options || [];
    const links = [
        ...(data.links || []),
        ...(data.messages || []).flatMap((message) => message.links || []),
    ];
    const linkLabels = [...new Set(links.map((link) => link.label?.trim()).filter(Boolean))];
    const connectionHandleProps = (direction, handleId, label) => ({
        title: label,
        'aria-label': label,
        role: 'button',
        tabIndex: 0,
        onPointerDown: (event) => {
            pointerStartRef.current = { x: event.clientX, y: event.clientY };
        },
        onClick: (event) => {
            const start = pointerStartRef.current;
            pointerStartRef.current = null;
            if (start && Math.hypot(event.clientX - start.x, event.clientY - start.y) > 4) return;

            event.stopPropagation();
            navigateConnection?.({ nodeId: id, handleId, direction });
        },
        onKeyDown: (event) => {
            if (!['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            event.stopPropagation();
            navigateConnection?.({ nodeId: id, handleId, direction });
        },
    });

    return (
        <article className={`flow-node flow-node-${meta.className} ${selected ? 'selected' : ''}`}>
            {type !== 'trigger' && (
                <Handle
                    type="target"
                    position={Position.Left}
                    className="node-handle target-handle"
                    {...connectionHandleProps('incoming', null, 'Seguir para a etapa anterior')}
                />
            )}
            <header><span><Icon size={13} /> {meta.label}</span><MoreHorizontal size={15} /></header>
            <div className="flow-node-body">
                <strong>{data.label || meta.label}</strong>
                {data.text && <p>{data.text}</p>}
                {links.length > 0 && (
                    <div className="node-resource-list">
                        {linkLabels.length > 0
                            ? linkLabels.slice(0, 3).map((label) => <span className="node-resource-count" key={label}>{label}</span>)
                            : <span className="node-resource-count">{links.length} {links.length === 1 ? 'link' : 'links'}</span>}
                        {linkLabels.length > 3 && <span className="node-resource-more">+{linkLabels.length - 3}</span>}
                    </div>
                )}
                {type === 'trigger' && <div className="trigger-summary"><span />O usuário envia uma mensagem</div>}
                {type === 'menu' && (
                    <div className="node-options">
                        {options.map((option) => (
                            <div className="node-option" key={option.id}>
                                <span>{option.label}</span><ChevronRight size={13} />
                                <Handle
                                    type="source"
                                    id={option.id}
                                    position={Position.Right}
                                    className="node-handle option-handle"
                                    {...connectionHandleProps('outgoing', option.id, `Seguir conexão: ${option.label}`)}
                                />
                            </div>
                        ))}
                    </div>
                )}
                {type === 'action' && (
                    <div className="node-options">
                        {[['success', 'Sucesso'], ['error', 'Erro']].map(([handleId, handleLabel]) => (
                            <div className="node-option" key={handleId}>
                                <span>{handleLabel}</span><ChevronRight size={13} />
                                <Handle
                                    type="source"
                                    id={handleId}
                                    position={Position.Right}
                                    className="node-handle option-handle"
                                    {...connectionHandleProps('outgoing', handleId, `Saída: ${handleLabel}`)}
                                />
                            </div>
                        ))}
                    </div>
                )}
                {type === 'input' && data.variable && (
                    <div className="node-resource-list"><span className="node-resource-count">{'{{'}flow.{data.variable}{'}}'}</span></div>
                )}
            </div>
            <footer>{type === 'menu' ? `${options.length} opções` : type === 'trigger' ? 'Entrada' : type === 'input' ? 'Aguarda texto' : 'Próxima etapa'}</footer>
            {!['menu', 'action', 'handoff', 'end'].includes(type) && (
                <Handle
                    type="source"
                    position={Position.Right}
                    className="node-handle source-handle"
                    {...connectionHandleProps('outgoing', null, 'Seguir para a próxima etapa')}
                />
            )}
        </article>
    );
}

const nodeTypes = Object.fromEntries(Object.keys(nodeMeta).map((type) => [
    type,
    (props) => <FlowNode {...props} type={type} />,
]));

function FlowBuilder({ automationId, navigate, notify, flowActions = [] }) {
    const [automation, setAutomation] = useState(null);
    const [nodes, setNodes, onNodesChangeBase] = useNodesState([]);
    const [edges, setEdges, onEdgesChangeBase] = useEdgesState([]);
    const [selectedId, setSelectedId] = useState(null);
    const [activeTrailEdgeId, setActiveTrailEdgeId] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [saveState, setSaveState] = useState('saved');
    const [revision, setRevision] = useState(1);
    const revisionRef = useRef(1);
    const readyRef = useRef(false);
    const dirtyRef = useRef(false);
    const nodesRef = useRef(nodes);
    const edgesRef = useRef(edges);
    const flowInstanceRef = useRef(null);
    const trailTimeoutRef = useRef(null);
    const [palette, setPalette] = useState(false);
    const [simulator, setSimulator] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [validation, setValidation] = useState(null);

    useEffect(() => { nodesRef.current = nodes; }, [nodes]);
    useEffect(() => { edgesRef.current = edges; }, [edges]);
    useEffect(() => () => {
        if (trailTimeoutRef.current) window.clearTimeout(trailTimeoutRef.current);
    }, []);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/automations/${automationId}`);
            const detail = data.data;
            setAutomation(detail);
            setNodes(detail.draft?.graph?.nodes || []);
            setEdges(detail.draft?.graph?.edges || []);
            setRevision(detail.draft?.revision || 1);
            revisionRef.current = detail.draft?.revision || 1;
            setValidation(detail.draft?.validation);
            window.setTimeout(() => { readyRef.current = true; }, 80);
        } catch (error) {
            notify(errorMessage(error), 'error');
            navigate('automations');
        } finally {
            setLoading(false);
        }
    }, [automationId, navigate, notify, setEdges, setNodes]);
    useEffect(() => { load(); return () => { readyRef.current = false; }; }, [load]);

    const markDirty = useCallback(() => {
        if (readyRef.current) {
            dirtyRef.current = true;
            setSaveState('dirty');
        }
    }, []);

    const onNodesChange = useCallback((changes) => {
        onNodesChangeBase(changes);
        if (changes.some((change) => !['select', 'dimensions'].includes(change.type))) markDirty();
    }, [markDirty, onNodesChangeBase]);
    const onEdgesChange = useCallback((changes) => {
        onEdgesChangeBase(changes);
        if (changes.some((change) => change.type !== 'select')) markDirty();
    }, [markDirty, onEdgesChangeBase]);
    const onConnect = useCallback((connection) => {
        setEdges((current) => addEdge({
            ...connection,
            id: `edge_${Date.now()}`,
            type: 'smoothstep',
            data: connection.sourceHandle ? { optionKey: connection.sourceHandle } : {},
        }, current));
        markDirty();
    }, [markDirty, setEdges]);

    const navigateConnection = useCallback(({ nodeId, handleId, direction }) => {
        const edge = resolveConnectionEdge(edgesRef.current, { nodeId, handleId, direction });
        if (!edge) {
            notify('Este ponto ainda não está conectado.', 'error');
            return;
        }

        const destinationId = connectionDestination(edge, direction);
        if (!destinationId || !nodesRef.current.some((node) => node.id === destinationId)) {
            notify('A conexão aponta para uma etapa que não existe.', 'error');
            return;
        }

        setSelectedId(destinationId);
        setNodes((current) => current.map((node) => ({
            ...node,
            selected: node.id === destinationId,
        })));
        setActiveTrailEdgeId(edge.id);

        if (trailTimeoutRef.current) window.clearTimeout(trailTimeoutRef.current);
        trailTimeoutRef.current = window.setTimeout(() => {
            setActiveTrailEdgeId(null);
            trailTimeoutRef.current = null;
        }, 2200);

        window.requestAnimationFrame(() => {
            const instance = flowInstanceRef.current;
            const destinationNode = instance?.getNode(destinationId);
            if (!instance || !destinationNode) return;

            instance.fitView({
                nodes: [destinationNode],
                padding: 0.8,
                minZoom: 0.7,
                maxZoom: 1.05,
                duration: 550,
            });
        });
    }, [notify, setNodes]);

    const displayEdges = useMemo(() => edges.map((edge) => edge.id === activeTrailEdgeId
        ? {
            ...edge,
            className: [edge.className, 'trail-active'].filter(Boolean).join(' '),
            animated: true,
        }
        : edge), [activeTrailEdgeId, edges]);

    const serializeGraph = useCallback(() => ({
        schemaVersion: 1,
        viewport: automation?.draft?.graph?.viewport || { x: 0, y: 0, zoom: 0.8 },
        nodes: nodesRef.current.map(({ id, type, position, data }) => ({ id, type, position, data })),
        edges: edgesRef.current.map(({ id, source, target, sourceHandle, targetHandle, type, data, label }) => ({
            id, source, target, sourceHandle: sourceHandle || null, targetHandle: targetHandle || null,
            type: type || 'smoothstep', data: data || {}, ...(label ? { label } : {}),
        })),
    }), [automation]);

    const saveNow = useCallback(async () => {
        if (!dirtyRef.current || saving) return true;
        setSaving(true);
        setSaveState('saving');
        try {
            const { data } = await api.put(`/automations/${automationId}/graph`, {
                graph: serializeGraph(),
                expected_revision: revisionRef.current,
            });
            revisionRef.current = data.data.revision;
            setRevision(data.data.revision);
            setValidation(data.data.validation);
            dirtyRef.current = false;
            setSaveState('saved');
            return true;
        } catch (error) {
            setSaveState('error');
            notify(errorMessage(error), 'error');
            return false;
        } finally {
            setSaving(false);
        }
    }, [automationId, notify, saving, serializeGraph]);

    useEffect(() => {
        if (saveState !== 'dirty') return undefined;
        const timeout = window.setTimeout(saveNow, 1100);
        return () => window.clearTimeout(timeout);
    }, [nodes, edges, saveNow, saveState]);

    const addNode = (type) => {
        const id = `${type}_${Date.now()}`;
        const defaults = {
            trigger: { label: 'Novo gatilho', triggerType: 'incoming_message' },
            message: { label: 'Nova mensagem', text: 'Digite aqui a mensagem que será enviada.' },
            menu: {
                label: 'Novo menu',
                text: 'Selecione uma opção:',
                menuMode: 'list',
                options: [
                    { id: `opcao_${Date.now()}_1`, label: 'Opção 1' },
                    { id: `opcao_${Date.now()}_2`, label: 'Voltar' },
                ],
            },
            input: {
                label: 'Nova pergunta',
                text: 'Digite sua resposta:',
                variable: 'resposta',
                validation: 'any',
                optional: false,
            },
            condition: { label: 'Nova condição', field: 'contact.name', operator: 'filled' },
            action: { label: 'Registrar na ouvidoria', action: 'ouvidoria.submit' },
            delay: { label: 'Aguardar 5 minutos', seconds: 300 },
            handoff: { label: 'Atendimento humano', text: 'Encaminhei sua conversa para nossa equipe.' },
            end: { label: 'Finalizar conversa' },
        };
        setNodes((current) => [...current, {
            id,
            type,
            position: { x: 420 + (current.length % 4) * 330, y: 180 + current.length * 50 },
            data: defaults[type],
        }]);
        setSelectedId(id);
        setPalette(false);
        markDirty();
    };

    const updateSelected = useCallback((patch) => {
        setNodes((current) => current.map((node) => node.id === selectedId
            ? { ...node, data: { ...node.data, ...patch } }
            : node));
        markDirty();
    }, [markDirty, selectedId, setNodes]);

    const deleteSelected = () => {
        if (!selectedId) return;
        const target = nodesRef.current.find((node) => node.id === selectedId);
        if (target?.type === 'trigger') {
            notify('O gatilho obrigatório não pode ser excluído.', 'error');
            return;
        }
        setNodes((current) => current.filter((node) => node.id !== selectedId));
        setEdges((current) => current.filter((edge) => edge.source !== selectedId && edge.target !== selectedId));
        setSelectedId(null);
        markDirty();
    };

    const publish = async () => {
        setPublishing(true);
        try {
            const saved = await saveNow();
            if (!saved) return;
            const { data } = await api.post(`/automations/${automationId}/publish`);
            setAutomation(data.data);
            setNodes(data.data.draft.graph.nodes);
            setEdges(data.data.draft.graph.edges);
            revisionRef.current = data.data.draft.revision;
            setRevision(data.data.draft.revision);
            setValidation(data.data.draft.validation);
            dirtyRef.current = false;
            setSaveState('saved');
            notify(data.message);
        } catch (error) {
            notify(errorMessage(error), 'error');
            const validationErrors = error?.response?.data?.errors?.graph;
            if (validationErrors) setValidation({ valid: false, errors: validationErrors, warnings: [] });
        } finally {
            setPublishing(false);
        }
    };

    const selectedNode = nodes.find((node) => node.id === selectedId);
    if (loading) return <PageLoader full />;

    return (
        <div className="builder-shell">
            <header className="builder-topbar">
                <div className="builder-title">
                    <button className="icon-button" title="Voltar para automações" onClick={() => navigate('automations')}><ArrowLeft size={19} /></button>
                    <div>
                        <div className="builder-breadcrumb">Automações <ChevronRight size={13} /> Atendimento</div>
                        <strong>{automation?.name}</strong>
                    </div>
                    <StatusBadge status={automation?.status} />
                </div>
                <div className="builder-actions">
                    <span className={`save-status save-${saveState}`}>
                        {saveState === 'saving' && <LoaderCircle className="spin" size={14} />}
                        {saveState === 'saved' && <Check size={14} />}
                        {saveState === 'dirty' ? 'Alterações pendentes' : saveState === 'error' ? 'Erro ao salvar' : saveState === 'saving' ? 'Salvando…' : `Salvo · r${revision}`}
                    </span>
                    <button className="secondary-button compact" onClick={() => setSimulator(true)}><Play size={15} /> Testar fluxo</button>
                    <button className="primary-button compact publish-button" onClick={publish} disabled={publishing}>
                        {publishing ? <LoaderCircle className="spin" size={15} /> : <Rocket size={15} />} Publicar
                    </button>
                </div>
            </header>
            {automation?.status === 'active' && (
                <div className="published-banner">
                    Você está editando o rascunho. A versão {automation.published_version} continua publicada para os clientes.
                </div>
            )}
            <div className="builder-body">
                <aside className="builder-palette">
                    <div className="palette-heading"><span>Construção</span></div>
                    <button className="palette-primary" onClick={() => setPalette(!palette)}><Plus size={18} /> Adicionar etapa</button>
                    {palette && (
                        <div className="palette-menu">
                            {Object.entries(nodeMeta).filter(([type]) => type !== 'trigger').map(([type, meta]) => {
                                const Icon = meta.icon;
                                return <button key={type} onClick={() => addNode(type)}><span className={`palette-icon ${meta.className}`}><Icon size={17} /></span><div><strong>{meta.label}</strong><small>Adicionar ao canvas</small></div></button>;
                            })}
                        </div>
                    )}
                    <div className="palette-section">
                        <span>Gatilho ativo</span>
                        <article><div className="whatsapp-dot"><MessageCircle size={14} /></div><div><strong>Resposta padrão</strong><small>Qualquer mensagem recebida</small></div><i /></article>
                    </div>
                    <div className="palette-tips">
                        <MousePointer2 size={18} />
                        <strong>Conecte as etapas</strong>
                        <p>Arraste os pontos laterais entre os cartões para definir o caminho.</p>
                    </div>
                    {validation && (
                        <div className={`validation-summary ${validation.valid ? 'valid' : 'invalid'}`}>
                            {validation.valid ? <ShieldCheck size={18} /> : <CircleHelp size={18} />}
                            <div><strong>{validation.valid ? 'Estrutura válida' : `${validation.errors?.length || 0} pendência(s)`}</strong><span>{validation.warnings?.length || 0} aviso(s)</span></div>
                        </div>
                    )}
                </aside>
                <section className="flow-canvas">
                    <FlowNavigationContext.Provider value={navigateConnection}>
                        <ReactFlow
                            nodes={nodes}
                            edges={displayEdges}
                            onNodesChange={onNodesChange}
                            onEdgesChange={onEdgesChange}
                            onConnect={onConnect}
                            onInit={(instance) => { flowInstanceRef.current = instance; }}
                            onNodeClick={(_, node) => setSelectedId(node.id)}
                            onPaneClick={() => setSelectedId(null)}
                            nodeTypes={nodeTypes}
                            fitView
                            minZoom={0.15}
                            maxZoom={1.6}
                            defaultEdgeOptions={{ type: 'smoothstep', animated: false }}
                            proOptions={{ hideAttribution: true }}
                            deleteKeyCode={null}
                        >
                            <Background gap={22} size={1} color="#dbe2ea" />
                            <Controls showInteractive={false} />
                            <MiniMap
                                pannable
                                zoomable
                                nodeColor={(node) => node.type === 'trigger' ? '#22c55e' : node.type === 'menu' ? '#2563eb' : '#8b5cf6'}
                                maskColor="rgba(245,247,250,.78)"
                            />
                        </ReactFlow>
                    </FlowNavigationContext.Provider>
                    <div className="canvas-help"><MousePointer2 size={14} /> Clique em uma etapa para editar</div>
                </section>
                {selectedNode && (
                    <NodeInspector
                        node={selectedNode}
                        update={updateSelected}
                        onClose={() => setSelectedId(null)}
                        onDelete={deleteSelected}
                        flowActions={flowActions}
                    />
                )}
            </div>
            {simulator && (
                <Simulator
                    automationId={automationId}
                    title={automation?.name}
                    onClose={() => setSimulator(false)}
                    notify={notify}
                />
            )}
        </div>
    );
}

function LinksEditor({ links = [], onChange }) {
    const updateLink = (index, patch) => {
        const next = [...links];
        next[index] = { ...next[index], ...patch };
        onChange(next);
    };
    const removeLink = (index) => onChange(links.filter((_, itemIndex) => itemIndex !== index));
    const addLink = () => onChange([...links, { label: '', url: 'https://' }]);

    return (
        <div className="links-editor">
            {links.map((link, index) => (
                <div className="link-editor-row" key={`${link.url}-${index}`}>
                    <input
                        value={link.label || ''}
                        onChange={(event) => updateLink(index, { label: event.target.value })}
                        placeholder="Rótulo do link"
                        maxLength={80}
                    />
                    <input
                        value={link.url || ''}
                        onChange={(event) => updateLink(index, { url: event.target.value })}
                        placeholder="https://..."
                        inputMode="url"
                    />
                    <button onClick={() => removeLink(index)} title="Remover link"><Trash2 size={15} /></button>
                </div>
            ))}
            <button className="add-option" onClick={addLink}><Plus size={15} /> Adicionar link</button>
        </div>
    );
}

function NodeInspector({ node, update, onClose, onDelete, flowActions = [] }) {
    const meta = nodeMeta[node.type] || nodeMeta.message;
    const Icon = meta.icon;
    const updateOption = (index, patch) => {
        const options = [...(node.data.options || [])];
        options[index] = { ...options[index], ...patch };
        update({ options });
    };
    const addOption = () => {
        const stamp = Date.now();
        update({ options: [...(node.data.options || []), { id: `opcao_${stamp}`, label: 'Nova opção' }] });
    };
    const removeOption = (index) => update({ options: node.data.options.filter((_, itemIndex) => itemIndex !== index) });
    const updateMessage = (index, patch) => {
        const messages = [...(node.data.messages || [])];
        messages[index] = { ...messages[index], ...patch };
        update({ messages });
    };
    const addMessage = () => update({
        messages: [...(node.data.messages || []), { text: 'Nova mensagem', links: [] }],
    });
    const removeMessage = (index) => update({
        messages: node.data.messages.filter((_, itemIndex) => itemIndex !== index),
    });
    const hasMessageGroup = node.type === 'message' && (node.data.messages?.length || 0) > 0;

    return (
        <aside className="node-inspector">
            <header>
                <div className={`inspector-icon ${meta.className}`}><Icon size={18} /></div>
                <div><span>EDITAR ETAPA</span><strong>{meta.label}</strong></div>
                <button onClick={onClose} aria-label="Fechar editor da etapa"><PanelRightClose size={19} /></button>
            </header>
            <div className="inspector-scroll">
                <label>Nome interno</label>
                <input value={node.data.label || ''} onChange={(event) => update({ label: event.target.value })} maxLength={100} />
                {['message', 'menu', 'handoff', 'input'].includes(node.type) && !hasMessageGroup && (
                    <>
                        <label>{node.type === 'input' ? 'Pergunta enviada ao usuário' : 'Mensagem'}</label>
                        <textarea
                            value={node.data.text || ''}
                            onChange={(event) => update({ text: event.target.value })}
                            rows={7}
                            maxLength={4096}
                        />
                        <div className="field-counter">{(node.data.text || '').length}/4096</div>
                        <div className="variable-hint">Variáveis: <code>{'{{contact.name}}'}</code> <code>{'{{contact.phone}}'}</code> <code>{'{{flow.variavel}}'}</code></div>
                    </>
                )}
                {hasMessageGroup && (
                    <>
                        <div className="options-heading"><label>Mensagens do bloco</label><span>{node.data.messages.length}</span></div>
                        <div className="legacy-message-list">
                            {node.data.messages.map((message, index) => (
                                <section className="legacy-message-editor" key={`message-${index}`}>
                                    <header><strong>Mensagem {index + 1}</strong><button onClick={() => removeMessage(index)} title="Remover mensagem"><Trash2 size={15} /></button></header>
                                    <textarea
                                        value={message.text || ''}
                                        onChange={(event) => updateMessage(index, { text: event.target.value })}
                                        rows={4}
                                        maxLength={4096}
                                    />
                                    <LinksEditor
                                        links={message.links || []}
                                        onChange={(links) => updateMessage(index, { links })}
                                    />
                                </section>
                            ))}
                        </div>
                        <button className="add-option" onClick={addMessage}><Plus size={15} /> Adicionar mensagem</button>
                    </>
                )}
                {node.type === 'message' && !hasMessageGroup && (
                    <>
                        <div className="options-heading"><label>Links e downloads</label><span>{node.data.links?.length || 0}</span></div>
                        <LinksEditor links={node.data.links || []} onChange={(links) => update({ links })} />
                    </>
                )}
                {node.type === 'menu' && (
                    <>
                        <label>Texto antes do menu (opcional)</label>
                        <textarea
                            value={node.data.introText || ''}
                            onChange={(event) => update({ introText: event.target.value })}
                            rows={4}
                            maxLength={4096}
                            placeholder="Mensagem enviada antes da lista"
                        />
                        <label>Cabeçalho da lista</label>
                        <input
                            value={node.data.header || ''}
                            onChange={(event) => update({ header: event.target.value })}
                            maxLength={60}
                            placeholder="Opcional"
                        />
                        <label>Texto do botão</label>
                        <input
                            value={node.data.buttonLabel || ''}
                            onChange={(event) => update({ buttonLabel: event.target.value })}
                            maxLength={20}
                            placeholder="Selecione uma opção"
                        />
                        <label>Título da seção</label>
                        <input
                            value={node.data.sectionTitle || ''}
                            onChange={(event) => update({ sectionTitle: event.target.value })}
                            maxLength={24}
                            placeholder="Menu"
                        />
                        <label>Formato no WhatsApp</label>
                        <div className="segmented">
                            <button className={node.data.menuMode !== 'buttons' ? 'active' : ''} onClick={() => update({ menuMode: 'list' })}>Lista (até 10)</button>
                            <button className={node.data.menuMode === 'buttons' ? 'active' : ''} onClick={() => update({ menuMode: 'buttons' })}>Botões (até 3)</button>
                        </div>
                        <div className="options-heading"><label>Opções do menu</label><span>{node.data.options?.length || 0}</span></div>
                        <div className="inspector-options">
                            {(node.data.options || []).map((option, index) => (
                                <div className="inspector-option" key={`${option.id}-${index}`}>
                                    <span>{index + 1}</span>
                                    <div className="inspector-option-fields">
                                        <input
                                            value={option.label}
                                            onChange={(event) => updateOption(index, { label: event.target.value })}
                                            maxLength={24}
                                            placeholder="Título"
                                        />
                                        <input
                                            value={option.description || ''}
                                            onChange={(event) => updateOption(index, { description: event.target.value })}
                                            maxLength={72}
                                            placeholder="Descrição opcional"
                                        />
                                    </div>
                                    <button onClick={() => removeOption(index)} title="Remover opção"><Trash2 size={15} /></button>
                                </div>
                            ))}
                        </div>
                        <button
                            className="add-option"
                            onClick={addOption}
                            disabled={(node.data.options?.length || 0) >= (node.data.menuMode === 'buttons' ? 3 : 10)}
                        ><Plus size={15} /> Adicionar opção</button>
                        <label>Guardar escolha na variável (opcional)</label>
                        <input
                            value={node.data.saveTo || ''}
                            onChange={(event) => update({ saveTo: event.target.value.trim() || undefined })}
                            maxLength={40}
                            placeholder="Ex.: tipo"
                        />
                        <div className="variable-hint">O id da opção fica em <code>{'{{flow.' + (node.data.saveTo || 'variavel') + '}}'}</code> e o rótulo em <code>{'{{flow.' + (node.data.saveTo || 'variavel') + '_label}}'}</code>.</div>
                    </>
                )}
                {node.type === 'input' && (
                    <>
                        <label>Nome da variável</label>
                        <input
                            value={node.data.variable || ''}
                            onChange={(event) => update({ variable: event.target.value.trim().toLowerCase() })}
                            maxLength={40}
                            placeholder="Ex.: nome, cpf, descricao"
                        />
                        <div className="variable-hint">A resposta fica disponível como <code>{'{{flow.' + (node.data.variable || 'variavel') + '}}'}</code> nas próximas etapas.</div>
                        <label>Validação da resposta</label>
                        <select value={node.data.validation || 'any'} onChange={(event) => update({ validation: event.target.value })}>
                            <option value="any">Qualquer texto</option>
                            <option value="text">Texto não vazio</option>
                            <option value="cpf">CPF</option>
                            <option value="email">E-mail</option>
                            <option value="phone">Telefone</option>
                        </select>
                        <label>Tamanho máximo da resposta</label>
                        <input
                            type="number"
                            min="1"
                            max="5000"
                            value={node.data.maxLength ?? 5000}
                            onChange={(event) => update({ maxLength: Number(event.target.value) })}
                        />
                        <label className="check-row">
                            <input
                                type="checkbox"
                                checked={Boolean(node.data.optional)}
                                onChange={(event) => update({ optional: event.target.checked })}
                            />
                            Opcional — usuário pode enviar 0 para pular
                        </label>
                        <label>Texto quando a resposta é inválida (opcional)</label>
                        <textarea
                            value={node.data.retryText || ''}
                            onChange={(event) => update({ retryText: event.target.value })}
                            rows={3}
                            maxLength={4096}
                            placeholder="Repete a pergunta se vazio"
                        />
                    </>
                )}
                {node.type === 'action' && (
                    <>
                        <label>Ação executada</label>
                        <select
                            value={node.data.action || ''}
                            onChange={(event) => update({ action: event.target.value })}
                        >
                            <option value="">Selecione…</option>
                            {(flowActions.length ? flowActions : [{ id: 'ouvidoria.submit', label: 'Registrar na Ouvidoria (MAISSDoc)' }]).map((action) => (
                                <option key={action.id} value={action.id}>{action.label}</option>
                            ))}
                        </select>
                        {(flowActions.find((a) => a.id === node.data.action)?.description) && (
                            <div className="variable-hint">{flowActions.find((a) => a.id === node.data.action).description}</div>
                        )}
                        <div className="variable-hint">Conecte a saída <strong>Sucesso</strong> e a saída <strong>Erro</strong> do nó às próximas etapas.</div>
                    </>
                )}
                {node.type === 'message' && (
                    <>
                        <label>Botão após a mensagem (opcional)</label>
                        <input
                            value={node.data.continueLabel || ''}
                            onChange={(event) => update({ continueLabel: event.target.value })}
                            maxLength={20}
                            placeholder="Ex.: Voltar"
                        />
                    </>
                )}
                {node.type === 'delay' && (
                    <>
                        <label>Tempo em segundos</label>
                        <input type="number" min="1" value={node.data.seconds || 1} onChange={(event) => update({ seconds: Number(event.target.value) })} />
                    </>
                )}
                {node.type === 'condition' && (
                    <>
                        <label>Campo</label>
                        <input value={node.data.field || ''} onChange={(event) => update({ field: event.target.value })} />
                        <label>Operador</label>
                        <select value={node.data.operator || 'filled'} onChange={(event) => update({ operator: event.target.value })}>
                            <option value="filled">Está preenchido</option>
                            <option value="equals">É igual a</option>
                            <option value="contains">Contém</option>
                        </select>
                    </>
                )}
            </div>
            <footer>
                <button className="danger-button" onClick={onDelete} disabled={node.type === 'trigger'}><Trash2 size={16} /> Excluir etapa</button>
                <button className="primary-button compact" onClick={onClose}>Concluído</button>
            </footer>
        </aside>
    );
}

function Simulator({ automationId, title, onClose, notify }) {
    const [inputs, setInputs] = useState([]);
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(true);
    const [draft, setDraft] = useState('');
    const chatEnd = useRef(null);

    const run = useCallback(async (nextInputs) => {
        setLoading(true);
        try {
            const { data } = await api.post(`/automations/${automationId}/simulate`, { inputs: nextInputs });
            setResult(data.data);
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setLoading(false);
        }
    }, [automationId, notify]);
    useEffect(() => { run([]); }, [run]);
    useEffect(() => { chatEnd.current?.scrollIntoView({ behavior: 'smooth' }); }, [result]);

    const choose = (id) => {
        const next = [...inputs, id];
        setInputs(next);
        run(next);
    };
    const sendAnswer = () => {
        const text = draft.trim();
        if (!text) return;
        const next = [...inputs, text];
        setDraft('');
        setInputs(next);
        run(next);
    };
    const restart = () => { setInputs([]); setDraft(''); run([]); };
    const last = result?.transcript?.at(-1);
    const awaitingText = !loading && result?.status === 'waiting_input' && last?.type === 'input';

    return (
        <div className="simulator-overlay">
            <aside className="simulator-panel">
                <header>
                    <div className="simulator-avatar"><MessageCircle size={19} /></div>
                    <div><strong>Prévia no WhatsApp</strong><span>{title}</span></div>
                    <button onClick={restart} title="Reiniciar"><RefreshCw size={18} /></button>
                    <button onClick={onClose} aria-label="Fechar simulador"><X size={19} /></button>
                </header>
                <div className="phone-preview">
                    <div className="phone-chat-header">
                        <div className="phone-avatar">S</div>
                        <div><strong>Suporte Online</strong><span>conta comercial</span></div>
                    </div>
                    <div className="chat-wallpaper">
                        {result?.transcript?.map((item, index) => (
                            <div key={`${item.node_id || 'item'}-${index}`} className={`sim-message ${item.direction}`}>
                                <div className="sim-bubble">
                                    {item.header && <strong className="sim-header">{item.header}</strong>}
                                    <p>{item.text}</p>
                                    {!!item.links?.length && (
                                        <div className="sim-links">
                                            {item.links.map((link, linkIndex) => (
                                                <a
                                                    key={`${link.url}-${linkIndex}`}
                                                    href={link.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >{link.label || link.url}</a>
                                            ))}
                                        </div>
                                    )}
                                    {item.type === 'menu' && index === result.transcript.length - 1 && (
                                        <div className="sim-options">
                                            {item.options?.map((option) => (
                                                <button key={option.id} onClick={() => choose(option.id)}>{option.label}</button>
                                            ))}
                                        </div>
                                    )}
                                    <time>agora {item.direction === 'outbound' && <Check size={11} />}</time>
                                </div>
                            </div>
                        ))}
                        {loading && <div className="typing-indicator"><span /><span /><span /></div>}
                        {!loading && result?.status === 'human_handoff' && <div className="sim-system">Transferido para atendimento humano</div>}
                        {!loading && result?.status === 'completed' && <div className="sim-system">Fluxo finalizado</div>}
                        <div ref={chatEnd} />
                    </div>
                    {awaitingText ? (
                        <div className="phone-composer active">
                            <input
                                value={draft}
                                onChange={(event) => setDraft(event.target.value)}
                                onKeyDown={(event) => { if (event.key === 'Enter') sendAnswer(); }}
                                placeholder="Digite a resposta…"
                                autoFocus
                            />
                            <button onClick={sendAnswer} aria-label="Enviar resposta"><Send size={17} /></button>
                        </div>
                    ) : (
                        <div className="phone-composer"><span>Mensagem</span><Send size={17} /></div>
                    )}
                </div>
                <footer>
                    <span><ShieldCheck size={15} /> Simulação local: nenhuma mensagem real será enviada.</span>
                    {last?.type === 'menu' && <small>Escolha uma opção acima para continuar.</small>}
                    {awaitingText && <small>Digite a resposta da pergunta acima.</small>}
                    {last?.type === 'action' && <small>Ação executada em modo simulado — nenhuma chamada externa foi feita.</small>}
                </footer>
            </aside>
        </div>
    );
}

function InboxPage({ initialId, notify }) {
    const [conversations, setConversations] = useState([]);
    const [selectedId, setSelectedId] = useState(initialId || null);
    const [conversation, setConversation] = useState(null);
    const [loading, setLoading] = useState(true);
    const [text, setText] = useState('');
    const [sending, setSending] = useState(false);
    const [changingMode, setChangingMode] = useState(false);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');
    const [detailsOpen, setDetailsOpen] = useState(true);
    const [mobileChatOpen, setMobileChatOpen] = useState(Boolean(initialId));
    const messagesRef = useRef(null);
    const replyInputRef = useRef(null);

    const loadList = useCallback(async (silent = false) => {
        if (!silent) setLoading(true);
        try {
            const { data } = await api.get('/inbox', { params: { search, filter } });
            setConversations(data.data);
            setSelectedId((current) => {
                if (current && data.data.some((item) => item.public_id === current)) return current;
                if (window.matchMedia('(max-width: 680px)').matches) return null;
                return data.data[0]?.public_id || null;
            });
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            if (!silent) setLoading(false);
        }
    }, [filter, notify, search]);

    const loadConversation = useCallback(async (silent = false) => {
        if (!selectedId) {
            setConversation(null);
            return;
        }

        if (!silent) setConversation(null);
        try {
            const { data } = await api.get(`/inbox/${selectedId}`);
            setConversation(data.data);
        } catch (error) {
            notify(errorMessage(error), 'error');
        }
    }, [notify, selectedId]);

    useEffect(() => {
        const timeout = window.setTimeout(loadList, 250);
        return () => window.clearTimeout(timeout);
    }, [loadList]);
    useEffect(() => { loadConversation(); }, [loadConversation]);
    useEffect(() => {
        const interval = window.setInterval(() => {
            loadList(true);
            loadConversation(true);
        }, 5000);
        return () => window.clearInterval(interval);
    }, [loadConversation, loadList]);
    useEffect(() => {
        const container = messagesRef.current;
        if (container) container.scrollTop = container.scrollHeight;
    }, [conversation?.messages?.length, selectedId]);
    useEffect(() => {
        if (conversation?.can_reply) replyInputRef.current?.focus();
    }, [conversation?.can_reply]);

    const take = async () => {
        setChangingMode(true);
        try {
            await api.post(`/inbox/${selectedId}/take`);
            await Promise.all([loadConversation(true), loadList(true)]);
            notify('Conversa assumida por você. O campo de resposta está liberado.');
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setChangingMode(false);
        }
    };
    const changeMode = async (status) => {
        setChangingMode(true);
        try {
            await api.put(`/inbox/${selectedId}/mode`, { status });
            await Promise.all([loadConversation(true), loadList(true)]);
            notify(status === 'bot'
                ? 'Automação reativada.'
                : status === 'closed'
                    ? 'Conversa encerrada.'
                    : 'Atendimento humano ativado. Você já pode responder.');
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setChangingMode(false);
        }
    };
    const send = async (event) => {
        event.preventDefault();
        if (!text.trim() || !conversation?.can_reply) return;
        setSending(true);
        try {
            const { data } = await api.post(`/inbox/${selectedId}/reply`, { text });
            setText('');
            await Promise.all([loadConversation(true), loadList(true)]);
            if (data.data.status === 'failed') {
                notify('A mensagem foi registrada, mas a Meta recusou o envio. Verifique o erro exibido na conversa.', 'error');
            }
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setSending(false);
        }
    };
    const handleReplyKeyDown = (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            event.currentTarget.form?.requestSubmit();
        }
    };

    return (
        <div className={`inbox-shell ${detailsOpen ? '' : 'details-closed'}`}>
            <section className="conversation-list">
                <header><div><p className="eyebrow">ATENDIMENTO</p><h1>Caixa de entrada</h1></div><button title="Atualizar conversas" onClick={() => loadList()}><RefreshCw size={18} /></button></header>
                <div className="inbox-search"><Search size={16} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar conversa…" /></div>
                <div className="inbox-tabs">
                    {[['all', 'Todas'], ['mine', 'Minhas'], ['unread', 'Não lidas']].map(([value, label]) => (
                        <button key={value} className={filter === value ? 'active' : ''} onClick={() => setFilter(value)}>{label}</button>
                    ))}
                </div>
                <div className="conversation-scroll">
                    {loading && <PageLoader />}
                    {conversations.map((item) => (
                        <button key={item.public_id} className={selectedId === item.public_id ? 'active' : ''} onClick={() => { setSelectedId(item.public_id); setMobileChatOpen(true); }}>
                            <div className="contact-avatar">{item.contact?.name?.slice(0, 1) || '?'}</div>
                            <div className="conversation-summary">
                                <div><strong>{item.contact?.name || item.contact?.phone_number}</strong><time>{formatDate(item.last_message_at, { day: undefined, month: undefined })}</time></div>
                                <p>{item.last_message?.content?.text || 'Nova conversa'}</p>
                                <span className={`tiny-mode ${item.status}`}>{item.status === 'bot' ? 'Automação' : item.status === 'human' ? 'Humano' : 'Encerrado'}</span>
                            </div>
                            {item.unread_count > 0 && <em>{item.unread_count}</em>}
                        </button>
                    ))}
                    {!loading && !conversations.length && <EmptyState icon={MessageSquare} title="Nenhuma conversa" text="As mensagens recebidas aparecerão aqui." />}
                </div>
            </section>
            <section className={`chat-panel ${mobileChatOpen ? 'mobile-chat-visible' : ''}`}>
                {conversation ? (
                    <>
                        <header className="chat-header">
                            <button className="mobile-chat-back" aria-label="Voltar para conversas" onClick={() => { setMobileChatOpen(false); setSelectedId(null); }}><ArrowLeft size={18} /></button>
                            <div className="contact-avatar large">{conversation.contact?.name?.slice(0, 1) || '?'}</div>
                            <div><strong>{conversation.contact?.name || conversation.contact?.phone_number}</strong><span><i /> {conversation.contact?.phone_number}</span></div>
                            <div className="chat-actions">
                                <StatusBadge status={conversation.status} />
                                {conversation.status !== 'human' && conversation.status !== 'closed' && <button className="secondary-button compact" onClick={take} disabled={changingMode}><UserCheck size={15} /> Assumir</button>}
                                <button className="icon-button" title={detailsOpen ? 'Ocultar detalhes' : 'Exibir detalhes'} onClick={() => setDetailsOpen((open) => !open)}><PanelRightClose size={19} /></button>
                            </div>
                        </header>
                        <div className="chat-messages" ref={messagesRef}>
                            <div className="chat-day">Hoje</div>
                            {conversation.messages?.map((message) => (
                                <div key={message.id} className={`chat-message ${message.direction}`}>
                                    <div>
                                        <p>{message.content?.text || `[${message.type}]`}</p>
                                        {message.content?.options && (
                                            <div className="message-options">{message.content.options.map((option) => <span key={option.id}>{option.label}</span>)}</div>
                                        )}
                                        {message.status === 'failed' && <small className="message-error">Falha no envio pela Meta</small>}
                                        <time>{formatDate(message.created_at, { day: undefined, month: undefined })} {message.direction === 'outbound' && <Check size={12} />}</time>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <form className="chat-composer" onSubmit={send}>
                            {conversation.status !== 'human' && conversation.status !== 'closed' && <div className="manual-mode-warning"><UserCheck size={15} /> Clique em “Assumir” ou “Atendimento humano” para responder.</div>}
                            {conversation.status === 'closed' && <div className="manual-mode-warning"><CheckCircle2 size={15} /> Esta conversa está encerrada. Reative a automação para aguardar uma nova mensagem.</div>}
                            {!conversation.window_open && <div className="window-warning"><Clock3 size={15} /> Janela de 24 horas encerrada. É necessário usar um template aprovado.</div>}
                            <div>
                                <textarea
                                    ref={replyInputRef}
                                    value={text}
                                    onChange={(event) => setText(event.target.value)}
                                    onKeyDown={handleReplyKeyDown}
                                    placeholder={conversation.can_reply ? 'Digite uma mensagem…' : 'Ative o atendimento humano para responder'}
                                    rows={1}
                                    disabled={!conversation.can_reply || changingMode}
                                />
                                <button className="send-button" title="Enviar mensagem" disabled={sending || !conversation.can_reply || !text.trim()}>{sending ? <LoaderCircle className="spin" size={18} /> : <Send size={18} />}</button>
                            </div>
                        </form>
                    </>
                ) : <EmptyState icon={MessageSquare} title="Selecione uma conversa" text="Abra um atendimento para visualizar as mensagens." />}
            </section>
            {conversation && (
                <aside className="conversation-details">
                    <header><strong>Detalhes</strong><button title="Ocultar detalhes" onClick={() => setDetailsOpen(false)}><X size={17} /></button></header>
                    <div className="details-profile">
                        <div className="contact-avatar xlarge">{conversation.contact?.name?.slice(0, 1) || '?'}</div>
                        <strong>{conversation.contact?.name || 'Sem nome'}</strong>
                        <span>{conversation.contact?.phone_number}</span>
                    </div>
                    <div className="details-section">
                        <label>Responsável</label>
                        <div className="assignee-box"><UserRound size={17} /><span>{conversation.assignee?.name || 'Não atribuído'}</span></div>
                    </div>
                    <div className="details-section">
                        <label>Controle da conversa</label>
                        <button className={conversation.status === 'bot' ? 'active-control' : ''} onClick={() => changeMode('bot')} disabled={changingMode}><Bot size={17} /><span><strong>Automação</strong><small>Fluxo responde ao contato</small></span></button>
                        <button className={conversation.status === 'human' ? 'active-control' : ''} onClick={() => changeMode('human')} disabled={changingMode}><UserCheck size={17} /><span><strong>Atendimento humano</strong><small>Pausar a automação e liberar resposta</small></span></button>
                    </div>
                    <div className="details-section danger-zone">
                        <button onClick={() => changeMode('closed')} disabled={changingMode || conversation.status === 'closed'}><CheckCircle2 size={17} /> Encerrar conversa</button>
                    </div>
                </aside>
            )}
        </div>
    );
}

function ContactsPage({ notify }) {
    const [contacts, setContacts] = useState([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });

    useEffect(() => {
        setLoading(true);
        const timeout = window.setTimeout(() => {
            api.get('/contacts', { params: { search, page } })
                .then(({ data }) => {
                    setContacts(data.data);
                    setMeta({
                        current_page: data.current_page,
                        last_page: data.last_page,
                        total: data.total,
                    });
                })
                .catch((error) => notify(errorMessage(error), 'error'))
                .finally(() => setLoading(false));
        }, 250);
        return () => window.clearTimeout(timeout);
    }, [notify, page, search]);

    const changeSearch = (value) => {
        setSearch(value);
        setPage(1);
    };
    const exportUrl = `/api/app/contacts/export?search=${encodeURIComponent(search)}`;

    return (
        <div className="standard-page">
            <PageHeader eyebrow="CONTATOS" title="Base de contatos" description="Pessoas que interagiram com o número oficial da empresa." actions={<a className="secondary-button" href={exportUrl}><ExternalLink size={16} /> Exportar CSV</a>} />
            <div className="panel contacts-panel">
                <div className="table-toolbar">
                    <div className="search-field"><Search size={17} /><input value={search} onChange={(event) => changeSearch(event.target.value)} placeholder="Nome, telefone ou e-mail…" /></div>
                    <span>{meta.total} contato(s)</span>
                </div>
                {loading ? <PageLoader /> : (
                    <div className="data-table">
                        <div className="table-row table-head"><span>Contato</span><span>Telefone</span><span>Opt-in</span><span>Conversas</span><span>Última interação</span></div>
                        {contacts.map((contact) => (
                            <div className="table-row" key={contact.public_id}>
                                <span className="contact-cell"><div className="contact-avatar">{contact.name?.slice(0, 1) || '?'}</div><div><strong>{contact.name || 'Sem nome'}</strong><small>{contact.email || 'WhatsApp'}</small></div></span>
                                <span>{contact.phone_number}</span>
                                <span><span className={`opt-status ${contact.opt_in ? 'yes' : 'no'}`}>{contact.opt_in ? 'Autorizado' : 'Sem opt-in'}</span></span>
                                <span>{contact.conversations_count}</span>
                                <span>{formatDate(contact.last_seen_at)}</span>
                            </div>
                        ))}
                        {!contacts.length && <EmptyState icon={Users} title="Nenhum contato" text="Os contatos serão criados a partir dos webhooks recebidos." />}
                    </div>
                )}
                {meta.last_page > 1 && (
                    <div className="pagination-row">
                        <button className="secondary-button compact" disabled={meta.current_page <= 1} onClick={() => setPage((current) => current - 1)}>Anterior</button>
                        <span>Página {meta.current_page} de {meta.last_page}</span>
                        <button className="secondary-button compact" disabled={meta.current_page >= meta.last_page} onClick={() => setPage((current) => current + 1)}>Próxima</button>
                    </div>
                )}
            </div>
        </div>
    );
}

function SettingsPage({ channel, onUpdated, notify }) {
    const [activeTab, setActiveTab] = useState('channel');
    const tabs = {
        channel: {
            title: 'Canal do WhatsApp',
            description: 'Conecte diretamente o app criado na Meta ao seu ambiente próprio.',
        },
        team: {
            title: 'Equipe e permissões',
            description: 'Gerencie os acessos administrativos e os responsáveis pelo atendimento.',
        },
        security: {
            title: 'Segurança e operação',
            description: 'Acompanhe filas, webhooks e a trilha de auditoria do workspace.',
        },
    };

    return (
        <div className="standard-page settings-page">
            <PageHeader
                eyebrow="CONFIGURAÇÕES"
                title={tabs[activeTab].title}
                description={tabs[activeTab].description}
                actions={activeTab === 'channel' ? <StatusBadge status={channel?.status || 'disconnected'} /> : null}
            />
            <div className="settings-layout">
                <aside className="settings-nav">
                    <button className={activeTab === 'channel' ? 'active' : ''} onClick={() => setActiveTab('channel')}><MessageCircle size={17} /> Canal WhatsApp</button>
                    <button className={activeTab === 'team' ? 'active' : ''} onClick={() => setActiveTab('team')}><Users size={17} /> Equipe e permissões</button>
                    <button className={activeTab === 'security' ? 'active' : ''} onClick={() => setActiveTab('security')}><ShieldCheck size={17} /> Segurança e auditoria</button>
                </aside>
                {activeTab === 'channel' && <ChannelSettings channel={channel} onUpdated={onUpdated} notify={notify} />}
                {activeTab === 'team' && <TeamSettings notify={notify} />}
                {activeTab === 'security' && <SecuritySettings notify={notify} />}
            </div>
        </div>
    );
}

function ChannelSettings({ channel, onUpdated, notify }) {
    const [form, setForm] = useState({
        name: channel?.name || 'WhatsApp principal',
        phone_number_id: channel?.phone_number_id || '',
        waba_id: channel?.waba_id || '',
        display_phone_number: channel?.display_phone_number || '',
        graph_version: channel?.graph_version || 'v25.0',
        access_token: '',
        app_secret: '',
        verify_token: '',
    });
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const webhookUrl = `${window.location.origin}/api/webhooks/whatsapp`;
    useEffect(() => {
        setForm((current) => ({
            ...current,
            name: channel?.name || 'WhatsApp principal',
            phone_number_id: channel?.phone_number_id || '',
            waba_id: channel?.waba_id || '',
            display_phone_number: channel?.display_phone_number || '',
            graph_version: channel?.graph_version || 'v25.0',
        }));
    }, [channel]);
    const update = (field, value) => setForm((current) => ({ ...current, [field]: value }));
    const save = async (event) => {
        event.preventDefault();
        setSaving(true);
        try {
            await api.put('/channel', form);
            notify('Configuração salva com os segredos criptografados.');
            setForm((current) => ({ ...current, access_token: '', app_secret: '', verify_token: '' }));
            await onUpdated();
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setSaving(false);
        }
    };
    const test = async () => {
        setTesting(true);
        try {
            const { data } = await api.post('/channel/test');
            notify(data.message);
            await onUpdated();
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setTesting(false);
        }
    };
    const copyWebhook = () => navigator.clipboard.writeText(webhookUrl)
        .then(() => notify('URL do webhook copiada.'))
        .catch(() => notify('Não foi possível copiar automaticamente. Selecione a URL manualmente.', 'error'));

    return (
        <form className="settings-form" onSubmit={save}>
            <section className="settings-section">
                <div className="section-title"><div className="section-icon green"><MessageCircle size={19} /></div><div><h3>Identificação do canal</h3><p>Dados encontrados em WhatsApp &gt; Configuração da API no painel da Meta.</p></div></div>
                <div className="form-grid">
                    <Field label="Nome interno" value={form.name} onChange={(value) => update('name', value)} placeholder="WhatsApp principal" />
                    <Field label="Número exibido" value={form.display_phone_number} onChange={(value) => update('display_phone_number', value)} placeholder="+55 17 0000-0000" />
                    <Field label="Phone Number ID" value={form.phone_number_id} onChange={(value) => update('phone_number_id', value)} placeholder="Identificador numérico da Meta" />
                    <Field label="WABA ID" value={form.waba_id} onChange={(value) => update('waba_id', value)} placeholder="ID da conta do WhatsApp Business" />
                    <Field label="Versão da Graph API" value={form.graph_version} onChange={(value) => update('graph_version', value)} placeholder="v25.0" />
                </div>
            </section>
            <section className="settings-section">
                <div className="section-title"><div className="section-icon violet"><KeyRound size={19} /></div><div><h3>Credenciais protegidas</h3><p>Campos vazios preservam o segredo já salvo. Os valores nunca retornam ao navegador.</p></div></div>
                <div className="form-grid one-column">
                    <SecretField label="Token permanente do usuário do sistema" value={form.access_token} onChange={(value) => update('access_token', value)} saved={channel?.has_access_token} />
                    <SecretField label="Chave secreta do aplicativo (App Secret)" value={form.app_secret} onChange={(value) => update('app_secret', value)} saved={channel?.has_app_secret} />
                    <SecretField label="Token de verificação do webhook" value={form.verify_token} onChange={(value) => update('verify_token', value)} saved={channel?.has_verify_token} />
                </div>
            </section>
            <section className="settings-section">
                <div className="section-title"><div className="section-icon blue"><Workflow size={19} /></div><div><h3>Webhook da Meta</h3><p>Cadastre esta URL e assine o campo <code>messages</code>.</p></div></div>
                <label className="field-label">URL de callback</label>
                <div className="copy-field"><code>{webhookUrl}</code><button type="button" onClick={copyWebhook}><Copy size={16} /> Copiar</button></div>
                <div className="security-note"><LockKeyhole size={18} /><p><strong>Validação ativa:</strong> toda notificação POST exige assinatura HMAC SHA-256 gerada com o App Secret. Eventos repetidos são ignorados por idempotência.</p></div>
            </section>
            <div className="settings-actions">
                <button type="button" className="secondary-button" onClick={test} disabled={testing || !channel?.configured}>{testing ? <LoaderCircle className="spin" size={16} /> : <RefreshCw size={16} />} Testar conexão</button>
                <button className="primary-button" disabled={saving}>{saving ? <LoaderCircle className="spin" size={16} /> : <ShieldCheck size={16} />} Salvar configuração</button>
            </div>
        </form>
    );
}

function TeamSettings({ notify }) {
    const [members, setMembers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [creating, setCreating] = useState(false);
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ name: '', email: '', password: '', role: 'agent' });

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/team');
            setMembers(data.data);
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setLoading(false);
        }
    }, [notify]);
    useEffect(() => { load(); }, [load]);

    const createMember = async (event) => {
        event.preventDefault();
        setCreating(true);
        try {
            await api.post('/team', form);
            setForm({ name: '', email: '', password: '', role: 'agent' });
            setShowForm(false);
            notify('Usuário criado e liberado para o workspace.');
            await load();
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setCreating(false);
        }
    };
    const updateMember = async (member, patch) => {
        try {
            await api.put(`/team/${member.id}`, {
                role: patch.role ?? member.role,
                is_active: patch.is_active ?? member.is_active,
            });
            notify('Permissões atualizadas.');
            await load();
        } catch (error) {
            notify(errorMessage(error), 'error');
        }
    };

    return (
        <div className="settings-form">
            <section className="settings-section">
                <div className="section-title split-title">
                    <div className="section-title-copy"><div className="section-icon blue"><Users size={19} /></div><div><h3>Usuários do workspace</h3><p>O master não pode ser desativado por esta tela.</p></div></div>
                    <button className="primary-button compact" onClick={() => setShowForm((visible) => !visible)}><Plus size={15} /> Novo usuário</button>
                </div>
                {showForm && (
                    <form className="team-create-form" onSubmit={createMember}>
                        <Field label="Nome" value={form.name} onChange={(value) => setForm((current) => ({ ...current, name: value }))} placeholder="Nome completo" />
                        <Field label="E-mail" value={form.email} onChange={(value) => setForm((current) => ({ ...current, email: value }))} placeholder="usuario@empresa.com.br" />
                        <label className="form-field"><span>Senha inicial</span><input type="password" minLength={12} value={form.password} onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))} placeholder="Mínimo de 12 caracteres" required /></label>
                        <label className="form-field"><span>Perfil</span><select value={form.role} onChange={(event) => setForm((current) => ({ ...current, role: event.target.value }))}><option value="agent">Atendente</option><option value="admin">Administrador</option></select></label>
                        <div className="team-form-actions"><button type="button" className="secondary-button compact" onClick={() => setShowForm(false)}>Cancelar</button><button className="primary-button compact" disabled={creating}>{creating && <LoaderCircle className="spin" size={15} />} Criar usuário</button></div>
                    </form>
                )}
                {loading ? <PageLoader /> : (
                    <div className="settings-table">
                        <div className="settings-table-row settings-table-head"><span>Usuário</span><span>Perfil</span><span>Status</span><span>Ação</span></div>
                        {members.map((member) => (
                            <div className="settings-table-row" key={member.id}>
                                <span className="member-cell"><strong>{member.name}</strong><small>{member.email}</small></span>
                                <span>
                                    <select value={member.role} disabled={member.role === 'owner'} onChange={(event) => updateMember(member, { role: event.target.value })}>
                                        {member.role === 'owner' && <option value="owner">Master</option>}
                                        <option value="admin">Administrador</option>
                                        <option value="agent">Atendente</option>
                                    </select>
                                </span>
                                <span><span className={`status-badge ${member.is_active ? 'status-connected' : 'status-error'}`}><i />{member.is_active ? 'Ativo' : 'Inativo'}</span></span>
                                <span><button className="secondary-button compact" disabled={member.role === 'owner'} onClick={() => updateMember(member, { is_active: !member.is_active })}>{member.is_active ? 'Desativar' : 'Ativar'}</button></span>
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}

function SecuritySettings({ notify }) {
    const [operations, setOperations] = useState(null);
    const [loading, setLoading] = useState(true);
    const load = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/operations');
            setOperations(data.data);
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setLoading(false);
        }
    }, [notify]);
    useEffect(() => { load(); }, [load]);

    if (loading) return <div className="settings-form"><section className="settings-section"><PageLoader /></section></div>;

    return (
        <div className="settings-form">
            <section className="settings-section">
                <div className="section-title split-title">
                    <div className="section-title-copy"><div className="section-icon green"><Activity size={19} /></div><div><h3>Operação das filas</h3><p>Processamento nativo do Laravel pelo gerenciador de filas do Plesk.</p></div></div>
                    <button className="secondary-button compact" onClick={load}><RefreshCw size={15} /> Atualizar</button>
                </div>
                <div className="operation-grid">
                    <article><span>Conexão</span><strong>{operations?.queue?.connection || '—'}</strong></article>
                    <article><span>Fila do webhook</span><strong>{operations?.queue?.webhook_queue || '—'}</strong></article>
                    <article><span>Pendentes</span><strong>{operations?.queue?.pending || 0}</strong></article>
                    <article className={operations?.queue?.failed > 0 ? 'operation-error' : ''}><span>Falhas</span><strong>{operations?.queue?.failed || 0}</strong></article>
                </div>
                <div className="security-note"><ShieldCheck size={18} /><p>No Plesk, habilite um worker Laravel para a conexão <strong>{operations?.queue?.connection}</strong> e fila <strong>{operations?.queue?.webhook_queue}</strong>. O processo passa a reiniciar automaticamente sem depender de uma sessão SSH.</p></div>
            </section>
            <section className="settings-section">
                <div className="section-title"><div className="section-icon violet"><Workflow size={19} /></div><div><h3>Webhooks</h3><p>Últimos sinais recebidos e processados pelo sistema.</p></div></div>
                <div className="operation-grid">
                    <article><span>Aguardando</span><strong>{operations?.webhooks?.received || 0}</strong></article>
                    <article className={operations?.webhooks?.failed > 0 ? 'operation-error' : ''}><span>Com falha</span><strong>{operations?.webhooks?.failed || 0}</strong></article>
                    <article><span>Último recebido</span><strong>{formatDate(operations?.webhooks?.last_received_at)}</strong></article>
                    <article><span>Último processado</span><strong>{formatDate(operations?.webhooks?.last_processed_at)}</strong></article>
                </div>
            </section>
            <section className="settings-section">
                <div className="section-title"><div className="section-icon blue"><ShieldCheck size={19} /></div><div><h3>Trilha de auditoria</h3><p>Últimas alterações administrativas do workspace.</p></div></div>
                <div className="audit-list">
                    {operations?.audit_logs?.map((log) => (
                        <div key={log.id}><span><strong>{log.action}</strong><small>{log.user} · {log.ip_address || 'IP interno'}</small></span><time>{formatDate(log.created_at)}</time></div>
                    ))}
                    {!operations?.audit_logs?.length && <EmptyState icon={ShieldCheck} title="Nenhum evento administrativo" text="As próximas alterações serão registradas aqui." />}
                </div>
            </section>
        </div>
    );
}

function Field({ label, value, onChange, placeholder }) {
    return <label className="form-field"><span>{label}</span><input value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} /></label>;
}

function SecretField({ label, value, onChange, saved }) {
    return (
        <label className="form-field secret-field">
            <span>{label} {saved && <em><Check size={12} /> salvo</em>}</span>
            <div><LockKeyhole size={16} /><input type="password" value={value} onChange={(event) => onChange(event.target.value)} placeholder={saved ? '••••••••••••••••••••' : 'Informe o segredo'} autoComplete="new-password" /></div>
        </label>
    );
}

function ProfileModal({ user, onClose, onUpdated, notify }) {
    const [name, setName] = useState(user?.name || '');
    const [passwordForm, setPasswordForm] = useState({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const [savingName, setSavingName] = useState(false);
    const [savingPassword, setSavingPassword] = useState(false);
    const roleLabel = {
        owner: 'Master',
        admin: 'Administrador',
        manager: 'Gestor',
        agent: 'Atendente',
    }[user?.role] || user?.role;

    const saveName = async (event) => {
        event.preventDefault();
        setSavingName(true);

        try {
            await api.put('/profile', { name });
            await onUpdated();
            notify('Nome atualizado com sucesso.');
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setSavingName(false);
        }
    };

    const savePassword = async (event) => {
        event.preventDefault();
        setSavingPassword(true);

        try {
            await api.put('/profile/password', passwordForm);
            setPasswordForm({
                current_password: '',
                password: '',
                password_confirmation: '',
            });
            notify('Senha atualizada com sucesso. As outras sessões foram encerradas.');
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            setSavingPassword(false);
        }
    };

    const updatePasswordField = (field, value) => {
        setPasswordForm((current) => ({ ...current, [field]: value }));
    };

    return (
        <Modal title="Meu perfil" onClose={onClose}>
            <div className="profile-modal-body">
                <section className="profile-summary">
                    <div className="profile-avatar">{name?.slice(0, 1) || 'U'}</div>
                    <div>
                        <strong>{name || user?.name}</strong>
                        <span>{user?.email}</span>
                    </div>
                    <span className="profile-role">{roleLabel}</span>
                </section>

                <form className="profile-form" onSubmit={saveName}>
                    <div className="profile-form-title">
                        <UserRound size={18} />
                        <div><strong>Dados pessoais</strong><span>Este nome aparece nos atendimentos e registros.</span></div>
                    </div>
                    <label className="form-field">
                        <span>Nome completo</span>
                        <input
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            minLength={2}
                            maxLength={120}
                            autoComplete="name"
                            required
                        />
                    </label>
                    <div className="profile-form-actions">
                        <button className="primary-button compact" disabled={savingName || name.trim().length < 2}>
                            {savingName && <LoaderCircle className="spin" size={15} />}
                            Salvar nome
                        </button>
                    </div>
                </form>

                <form className="profile-form" onSubmit={savePassword}>
                    <div className="profile-form-title">
                        <KeyRound size={18} />
                        <div><strong>Alterar senha</strong><span>Use pelo menos 12 caracteres, com letras, número e símbolo.</span></div>
                    </div>
                    <label className="form-field">
                        <span>Senha atual</span>
                        <input
                            type="password"
                            value={passwordForm.current_password}
                            onChange={(event) => updatePasswordField('current_password', event.target.value)}
                            autoComplete="current-password"
                            required
                        />
                    </label>
                    <div className="profile-password-grid">
                        <label className="form-field">
                            <span>Nova senha</span>
                            <input
                                type="password"
                                value={passwordForm.password}
                                onChange={(event) => updatePasswordField('password', event.target.value)}
                                minLength={12}
                                autoComplete="new-password"
                                required
                            />
                        </label>
                        <label className="form-field">
                            <span>Confirmar nova senha</span>
                            <input
                                type="password"
                                value={passwordForm.password_confirmation}
                                onChange={(event) => updatePasswordField('password_confirmation', event.target.value)}
                                minLength={12}
                                autoComplete="new-password"
                                required
                            />
                        </label>
                    </div>
                    <div className="profile-form-actions">
                        <button
                            className="primary-button compact"
                            disabled={savingPassword || passwordForm.password.length < 12}
                        >
                            {savingPassword && <LoaderCircle className="spin" size={15} />}
                            Atualizar senha
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}

function Modal({ title, children, onClose }) {
    return (
        <div className="modal-overlay" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <div className="modal" role="dialog" aria-modal="true" aria-label={title}>
                <header><h3>{title}</h3><button onClick={onClose} aria-label="Fechar janela"><X size={19} /></button></header>
                {children}
            </div>
        </div>
    );
}

function PageLoader({ full = false }) {
    return <div className={`page-loader ${full ? 'full' : ''}`}><LoaderCircle className="spin" size={23} /><span>Carregando…</span></div>;
}

createRoot(document.getElementById('app')).render(<App />);
