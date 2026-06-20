import { useEffect, useMemo, useState } from "react";
import { ACTIVITY_TYPES, INDUSTRIES, PIPELINE_STAGES, STATUSES, TIERS } from "./data";
import { Avatar, Button, Icon, Modal } from "./components";
import { isoToday, loadStore, money, saveStore } from "./utils";
import { CalendarView, ClientsView, Dashboard, EmailsView, GoalsView, InvoicesView, PipelineView } from "./views";
import { ClientModal, EmailModal, EventModal, GoalsModal, InvoiceModal } from "./modals";

const blankClient = () => ({ name: "", contact: "", email: "", phone: "", company: "", industry: "Tech", status: "Lead", tier: "Silver", deal: "", pipeline: "Outreach", tags: "", score: 50, assigned: "Hira", country: "", notes: "" });
const blankInvoice = () => ({ client: "", amount: "", due: "", status: "Pending" });
const blankEvent = () => ({ title: "", date: "", time: "", client: "", type: "Call" });

export default function App() {
  const [store, setStore] = useState(loadStore);
  const [view, setView] = useState("dashboard");
  const [selectedId, setSelectedId] = useState(null);
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState({ status: "All", tier: "All", industry: "All", sort: "score" });
  const [modal, setModal] = useState(null);
  const [draft, setDraft] = useState(blankClient);
  const [taskText, setTaskText] = useState("");
  const [activity, setActivity] = useState({ type: "Call", note: "" });
  const [toast, setToast] = useState(null);

  useEffect(() => { saveStore(store); }, [store]);

  const notify = (message, type = "success") => {
    setToast({ message, type });
    window.setTimeout(() => setToast(null), 2500);
  };

  const selected = store.clients.find((client) => client.id === selectedId) || null;

  const filteredClients = useMemo(() => store.clients.filter((client) => {
    const text = `${client.name} ${client.contact} ${client.company} ${client.email}`.toLowerCase();
    return (filters.status === "All" || client.status === filters.status)
      && (filters.tier === "All" || client.tier === filters.tier)
      && (filters.industry === "All" || client.industry === filters.industry)
      && (!search || text.includes(search.toLowerCase()));
  }).sort((a, b) => {
    if (filters.sort === "name") return a.name.localeCompare(b.name);
    if (filters.sort === "deal") return b.deal - a.deal;
    return b.score - a.score;
  }), [store.clients, filters, search]);

  const metrics = useMemo(() => {
    const closedRevenue = store.clients.filter((client) => client.pipeline === "Closed Won").reduce((sum, client) => sum + client.deal, 0);
    const pipelineValue = store.clients.filter((client) => !["Closed Won", "Closed Lost"].includes(client.pipeline)).reduce((sum, client) => sum + client.deal, 0);
    const active = store.clients.filter((client) => client.status === "Active").length;
    const score = Math.round(store.clients.reduce((sum, client) => sum + client.score, 0) / Math.max(store.clients.length, 1));
    return { closedRevenue, pipelineValue, active, score };
  }, [store.clients]);

  const updateStore = (key, value) => setStore((current) => ({ ...current, [key]: value }));
  const updateClient = (id, changes) => updateStore("clients", store.clients.map((client) => client.id === id ? { ...client, ...changes } : client));

  function openNewClient() { setDraft(blankClient()); setModal("newClient"); }
  function openEdit(client) { setDraft({ ...client, tags: client.tags.join(", ") }); setModal("editClient"); }
  function saveClient(edit = false) {
    if (!draft.name.trim()) return notify("Client name is required", "error");
    const normalized = { ...draft, deal: Number(draft.deal) || 0, score: Number(draft.score) || 0, tags: Array.isArray(draft.tags) ? draft.tags : draft.tags.split(",").map((tag) => tag.trim()).filter(Boolean) };
    if (edit) {
      updateClient(draft.id, normalized);
    } else {
      updateStore("clients", [{ ...normalized, id: Date.now(), joined: isoToday(), lastContact: isoToday(), tasks: [], activity: [{ type: "Note", note: "Client added to CRM", date: isoToday() }] }, ...store.clients]);
    }
    setModal(null);
    notify(edit ? "Client updated" : "Client added");
  }
  function removeClient() {
    updateStore("clients", store.clients.filter((client) => client.id !== selectedId));
    setSelectedId(null);
    setModal(null);
    notify("Client deleted", "error");
  }
  function addTask() {
    if (!taskText.trim() || !selected) return;
    updateClient(selected.id, { tasks: [...selected.tasks, { text: taskText.trim(), done: false }] });
    setTaskText("");
    notify("Task added");
  }
  function toggleTask(index) {
    if (!selected) return;
    updateClient(selected.id, { tasks: selected.tasks.map((task, i) => i === index ? { ...task, done: !task.done } : task) });
  }
  function addActivity() {
    if (!selected || !activity.note.trim()) return;
    updateClient(selected.id, { activity: [{ ...activity, date: isoToday() }, ...selected.activity], lastContact: isoToday() });
    setActivity({ type: "Call", note: "" });
    notify("Activity logged");
  }
  function sendEmail() {
    if (!draft.to || !draft.subject) return notify("Recipient and subject are required", "error");
    updateStore("emails", [{ ...draft, id: Date.now(), date: isoToday() }, ...store.emails]);
    setModal(null);
    notify("Email saved to CRM");
  }
  function createInvoice() {
    if (!draft.client || !draft.amount || !draft.due) return notify("Client, amount, and due date are required", "error");
    updateStore("invoices", [{ ...draft, id: `INV-${String(store.invoices.length + 1).padStart(3, "0")}`, amount: Number(draft.amount), issued: isoToday() }, ...store.invoices]);
    setModal(null);
    notify("Invoice created");
  }
  function createEvent() {
    if (!draft.title || !draft.date) return notify("Event title and date are required", "error");
    updateStore("events", [...store.events, { ...draft, id: Date.now() }].sort((a, b) => `${a.date}${a.time}`.localeCompare(`${b.date}${b.time}`)));
    setModal(null);
    notify("Event added");
  }

  const nav = [["dashboard", "dashboard", "Dashboard"], ["clients", "clients", "Clients"], ["pipeline", "pipeline", "Pipeline"], ["calendar", "calendar", "Calendar"], ["invoices", "invoices", "Invoices"], ["emails", "emails", "Emails"], ["goals", "goals", "Goals"]];

  return <div className="crm-shell">
    <aside className="sidebar">
      <div className="brand"><div className="brand-logo">E</div><div><strong>Elite Era</strong><span>Client CRM</span></div></div>
      <nav>{nav.map(([id, icon, label]) => <button key={id} className={view === id ? "nav-item active" : "nav-item"} onClick={() => { setView(id); setSelectedId(null); }}><Icon name={icon} />{label}</button>)}</nav>
      <div className="profile"><Avatar name="Hira Khyzer" size={34} /><div><strong>Hira Khyzer</strong><span>CEO & Founder</span></div></div>
    </aside>

    <main className="main">
      {view === "dashboard" && <Dashboard metrics={metrics} clients={store.clients} events={store.events} onViewClients={() => setView("clients")} onViewCalendar={() => setView("calendar")} onOpenClient={(id) => { setView("clients"); setSelectedId(id); }} />}
      {view === "clients" && <ClientsView clients={filteredClients} selected={selected} search={search} filters={filters} onSearch={setSearch} onFilters={setFilters} onSelect={setSelectedId} onAdd={openNewClient} onEdit={openEdit} onDelete={() => setModal("delete")} taskText={taskText} onTaskText={setTaskText} onAddTask={addTask} onToggleTask={toggleTask} activity={activity} onActivity={setActivity} onAddActivity={addActivity} />}
      {view === "pipeline" && <PipelineView clients={store.clients} onOpenClient={(id) => { setView("clients"); setSelectedId(id); }} onStageChange={(id, pipeline) => { updateClient(id, { pipeline }); notify("Pipeline updated"); }} />}
      {view === "calendar" && <CalendarView events={store.events} onNew={() => { setDraft(blankEvent()); setModal("event"); }} />}
      {view === "invoices" && <InvoicesView invoices={store.invoices} onNew={() => { setDraft(blankInvoice()); setModal("invoice"); }} onStatus={(id, status) => { updateStore("invoices", store.invoices.map((invoice) => invoice.id === id ? { ...invoice, status } : invoice)); notify("Invoice status updated"); }} />}
      {view === "emails" && <EmailsView emails={store.emails} clients={store.clients} onNew={() => { setDraft({ to: "", subject: "", body: "" }); setModal("email"); }} />}
      {view === "goals" && <GoalsView goals={store.goals} onEdit={() => { setDraft(store.goals.map((goal) => ({ ...goal }))); setModal("goals"); }} />}
      <footer className="footer"><strong>Made by Hira Khyzer</strong> · Elite Era Development L.L.C · <span>#f4af00</span></footer>
    </main>

    {modal === "newClient" && <ClientModal draft={draft} onDraft={setDraft} onClose={() => setModal(null)} onSave={() => saveClient(false)} />}
    {modal === "editClient" && <ClientModal title="Edit client" draft={draft} onDraft={setDraft} onClose={() => setModal(null)} onSave={() => saveClient(true)} />}
    {modal === "delete" && <Modal title="Delete client" onClose={() => setModal(null)}><p className="modal-copy">Are you sure you want to delete <strong>{selected?.name}</strong>? This action cannot be undone.</p><div className="modal-actions"><Button variant="outline" onClick={() => setModal(null)}>Cancel</Button><Button variant="danger" onClick={removeClient}>Delete client</Button></div></Modal>}
    {modal === "email" && <EmailModal draft={draft} onDraft={setDraft} clients={store.clients} onClose={() => setModal(null)} onSave={sendEmail} />}
    {modal === "invoice" && <InvoiceModal draft={draft} onDraft={setDraft} clients={store.clients} onClose={() => setModal(null)} onSave={createInvoice} />}
    {modal === "event" && <EventModal draft={draft} onDraft={setDraft} clients={store.clients} onClose={() => setModal(null)} onSave={createEvent} />}
    {modal === "goals" && <GoalsModal goals={draft} onClose={() => setModal(null)} onSave={(goals) => { updateStore("goals", goals); setModal(null); notify("Goals saved"); }} />}
    {toast && <div className={`toast ${toast.type}`}>{toast.message}</div>}
  </div>;
}
