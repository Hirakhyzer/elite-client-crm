import { GOLD, PIPELINE_STAGES, STATUSES, TIERS, INDUSTRIES, ACTIVITY_TYPES } from "./data";
import { Avatar, Button, Card, Chip, Icon, ScoreBar, SectionTitle, StatusBadge, TierBadge } from "./components";
import { dateLabel, money } from "./utils";

export function Dashboard({ metrics, clients, events, onViewClients, onViewCalendar, onOpenClient }) {
  const stageValue = (stage) => clients.filter((client) => client.pipeline === stage).reduce((sum, client) => sum + client.deal, 0);
  const max = Math.max(...PIPELINE_STAGES.map(stageValue), 1);

  return <div className="page">
    <div className="page-head"><div><h1>Good day, Hira <span>👋</span></h1><p>Here is your Elite Era CRM overview.</p></div></div>
    <div className="metrics">
      {[["Total Revenue", money(metrics.closedRevenue), "money", "gold"], ["Active Clients", metrics.active, "clients", "green"], ["Active Pipeline", money(metrics.pipelineValue), "chart", "blue"], ["Avg Lead Score", metrics.score, "star", "orange"]].map(([label, value, icon, tone]) => <Card key={label} className="metric"><div><span>{label}</span><strong>{value}</strong></div><Icon name={icon} className={tone} /></Card>)}
    </div>
    <Card className="funnel"><SectionTitle title="Pipeline funnel" />
      <div className="bars">{PIPELINE_STAGES.map((stage, index) => {
        const value = stageValue(stage);
        const height = Math.max((value / max) * 112, 10);
        const colors = [GOLD, "#ef9f27", "#1e73be", "#0f7c67", "#1a7a42", "#c23d3d"];
        return <div className="bar" key={stage}><span>{money(value).replace(",000", "k")}</span><i style={{ height: `${height}px`, background: colors[index] }} /><b>{stage}</b><small>{clients.filter((client) => client.pipeline === stage).length} clients</small></div>;
      })}</div>
    </Card>
    <div className="dashboard-grid">
      <Card><SectionTitle title="Recent clients" action={<Button variant="link" onClick={onViewClients}>View all →</Button>} />
        {clients.slice(0, 4).map((client) => <button className="client-mini" key={client.id} onClick={() => onOpenClient(client.id)}><Avatar name={client.name} /><span><strong>{client.name}</strong><small>{client.industry} · {money(client.deal)}</small></span><StatusBadge status={client.status} /></button>)}
      </Card>
      <Card><SectionTitle title="Upcoming" action={<Button variant="link" onClick={onViewCalendar}>Calendar →</Button>} />
        {events.slice(0, 4).map((event) => <div className="event-mini" key={event.id}><div><strong>{event.title}</strong><small>{event.client} · {dateLabel(event.date)} · {event.time}</small></div><span>{event.type}</span></div>)}
      </Card>
    </div>
  </div>;
}

export function ClientsView({ clients, selected, search, filters, onSearch, onFilters, onSelect, onAdd, onEdit, onDelete, taskText, onTaskText, onAddTask, onToggleTask, activity, onActivity, onAddActivity }) {
  return <div className="clients-layout">
    <section className="client-list">
      <div className="list-head"><div><h1>Clients</h1><p>{clients.length} matching contacts</p></div><Button onClick={onAdd}><Icon name="plus" /> Add client</Button></div>
      <div className="search"><Icon name="search" /><input value={search} onChange={(event) => onSearch(event.target.value)} placeholder="Search clients" /></div>
      <div className="filter-grid">
        <select value={filters.status} onChange={(event) => onFilters({ ...filters, status: event.target.value })}><option>All</option>{STATUSES.map((item) => <option key={item}>{item}</option>)}</select>
        <select value={filters.tier} onChange={(event) => onFilters({ ...filters, tier: event.target.value })}><option>All</option>{TIERS.map((item) => <option key={item}>{item}</option>)}</select>
        <select value={filters.industry} onChange={(event) => onFilters({ ...filters, industry: event.target.value })}><option>All</option>{INDUSTRIES.map((item) => <option key={item}>{item}</option>)}</select>
        <select value={filters.sort} onChange={(event) => onFilters({ ...filters, sort: event.target.value })}><option value="score">Lead score</option><option value="deal">Deal size</option><option value="name">Name</option></select>
      </div>
      <div className="client-scroll">
        {clients.map((client) => <button key={client.id} className={selected?.id === client.id ? "client-row selected" : "client-row"} onClick={() => onSelect(client.id)}><Avatar name={client.name} /><span className="client-summary"><strong>{client.name}</strong><small>{client.contact} · {client.industry}</small><span className="tags">{client.tags.slice(0, 2).map((tag) => <Chip key={tag}>{tag}</Chip>)}</span></span><span className="client-side"><StatusBadge status={client.status} /><b>{money(client.deal)}</b></span></button>)}
      </div>
    </section>
    <section className="detail">
      {selected ? <ClientDetail client={selected} onEdit={() => onEdit(selected)} onDelete={onDelete} taskText={taskText} onTaskText={onTaskText} onAddTask={onAddTask} onToggleTask={onToggleTask} activity={activity} onActivity={onActivity} onAddActivity={onAddActivity} /> : <div className="empty"><span>◉</span><h2>Select a client</h2><p>Choose a contact from the list to view their CRM record.</p></div>}
    </section>
  </div>;
}

function ClientDetail({ client, onEdit, onDelete, taskText, onTaskText, onAddTask, onToggleTask, activity, onActivity, onAddActivity }) {
  return <div className="detail-inner">
    <div className="detail-head"><div className="client-title"><Avatar name={client.name} size={50} /><div><h1>{client.name}</h1><p>{client.contact} · {client.email}</p></div></div><div className="head-actions"><Button variant="outline" onClick={onEdit}><Icon name="edit" /> Edit</Button><Button variant="danger-outline" onClick={onDelete}><Icon name="trash" /> Delete</Button></div></div>
    <div className="detail-stats"><div><span>Deal value</span><strong>{money(client.deal)}</strong></div><div><span>Pipeline</span><strong>{client.pipeline}</strong></div><div><span>Lead score</span><ScoreBar score={client.score} /></div></div>
    <div className="detail-grid">
      <Card><SectionTitle title="Profile" />
        <dl><div><dt>Company</dt><dd>{client.company || "—"}</dd></div><div><dt>Industry</dt><dd>{client.industry}</dd></div><div><dt>Country</dt><dd>{client.country || "—"}</dd></div><div><dt>Assigned</dt><dd>{client.assigned}</dd></div><div><dt>Status</dt><dd><StatusBadge status={client.status} /></dd></div><div><dt>Tier</dt><dd><TierBadge tier={client.tier} /></dd></div></dl>
        <p className="notes">{client.notes || "No notes yet."}</p>
      </Card>
      <Card><SectionTitle title="Tasks" />
        {client.tasks.length === 0 && <p className="muted">No tasks yet.</p>}
        {client.tasks.map((task, index) => <button className={task.done ? "task done" : "task"} key={`${task.text}-${index}`} onClick={() => onToggleTask(index)}><span>{task.done ? "✓" : ""}</span>{task.text}</button>)}
        <div className="add-inline"><input value={taskText} onChange={(event) => onTaskText(event.target.value)} placeholder="Add a task" onKeyDown={(event) => event.key === "Enter" && onAddTask()} /><Button onClick={onAddTask}><Icon name="plus" /></Button></div>
      </Card>
    </div>
    <Card><SectionTitle title="Activity timeline" />
      {client.activity.map((item, index) => <div className="activity" key={`${item.date}-${index}`}><span>{item.type}</span><div><strong>{item.note}</strong><small>{dateLabel(item.date)}</small></div></div>)}
      <div className="activity-form"><select value={activity.type} onChange={(event) => onActivity({ ...activity, type: event.target.value })}>{ACTIVITY_TYPES.map((type) => <option key={type}>{type}</option>)}</select><input value={activity.note} onChange={(event) => onActivity({ ...activity, note: event.target.value })} placeholder="Add a note, call, email, or meeting" /><Button onClick={onAddActivity}>Log activity</Button></div>
    </Card>
  </div>;
}

export function PipelineView({ clients, onOpenClient, onStageChange }) {
  return <div className="page"><div className="page-head"><div><h1>Pipeline</h1><p>Move leads through the Elite Era sales flow.</p></div></div><div className="kanban">
    {PIPELINE_STAGES.map((stage) => <div className="stage" key={stage}><div className="stage-head"><strong>{stage}</strong><span>{clients.filter((client) => client.pipeline === stage).length}</span></div>
      {clients.filter((client) => client.pipeline === stage).map((client) => <div className="deal-card" key={client.id}><button onClick={() => onOpenClient(client.id)}><Avatar name={client.name} /><div><strong>{client.name}</strong><small>{client.industry} · {money(client.deal)}</small></div></button><div><ScoreBar score={client.score} /><select value={client.pipeline} onChange={(event) => onStageChange(client.id, event.target.value)}>{PIPELINE_STAGES.map((item) => <option key={item}>{item}</option>)}</select></div></div>)}
    </div>)}
  </div></div>;
}

export function CalendarView({ events, onNew }) {
  return <div className="page"><div className="page-head split"><div><h1>Calendar</h1><p>Calls, proposal reviews, and client meetings.</p></div><Button onClick={onNew}><Icon name="plus" /> Add event</Button></div><Card><div className="calendar-list">
    {events.map((event) => <div className="calendar-event" key={event.id}><div className="date-badge"><b>{new Date(`${event.date}T12:00:00`).getDate()}</b><span>{new Date(`${event.date}T12:00:00`).toLocaleDateString("en-US", { month: "short" })}</span></div><div><strong>{event.title}</strong><p>{event.client || "No client"} · {event.type}</p></div><time>{event.time || "All day"}</time></div>)}
  </div></Card></div>;
}

export function InvoicesView({ invoices, onNew, onStatus }) {
  return <div className="page"><div className="page-head split"><div><h1>Invoices</h1><p>Track payments and billing status.</p></div><Button onClick={onNew}><Icon name="plus" /> New invoice</Button></div><Card><div className="table-wrap"><table><thead><tr><th>Invoice</th><th>Client</th><th>Issued</th><th>Due</th><th>Amount</th><th>Status</th></tr></thead><tbody>
    {invoices.map((invoice) => <tr key={invoice.id}><td><strong>{invoice.id}</strong></td><td>{invoice.client}</td><td>{dateLabel(invoice.issued)}</td><td>{dateLabel(invoice.due)}</td><td><strong>{money(invoice.amount)}</strong></td><td><select className={`invoice-status ${invoice.status.toLowerCase()}`} value={invoice.status} onChange={(event) => onStatus(invoice.id, event.target.value)}><option>Paid</option><option>Pending</option><option>Overdue</option></select></td></tr>)}
  </tbody></table></div></Card></div>;
}

export function EmailsView({ emails, clients, onNew }) {
  return <div className="page"><div className="page-head split"><div><h1>Emails</h1><p>Save outreach and follow-up drafts in one place.</p></div><Button onClick={onNew}><Icon name="mail" /> Compose email</Button></div><Card>
    {emails.length ? <div className="email-list">{emails.map((email) => <article key={email.id}><div><strong>{email.subject}</strong><span>To: {email.to} · {dateLabel(email.date)}</span></div><p>{email.body}</p></article>)}</div> : <div className="empty small"><span>✉</span><h2>No emails saved</h2><p>Compose a client email to keep the interaction history visible.</p></div>}
    <div className="quick-contacts"><h3>Quick recipients</h3>{clients.slice(0, 6).map((client) => <span key={client.id}>{client.name} · {client.email}</span>)}</div>
  </Card></div>;
}

export function GoalsView({ goals, onEdit }) {
  return <div className="page"><div className="page-head split"><div><h1>Goals</h1><p>Track important business milestones.</p></div><Button variant="outline" onClick={onEdit}><Icon name="edit" /> Edit goals</Button></div><div className="goal-grid">
    {goals.map((goal) => { const percent = Math.min(100, Math.round(goal.current / Math.max(goal.target, 1) * 100)); return <Card key={goal.label} className="goal"><span>{goal.label}</span><strong>{goal.prefix}{goal.current.toLocaleString()} <small>/ {goal.prefix}{goal.target.toLocaleString()}</small></strong><div className="goal-track"><i style={{ width: `${percent}%` }} /></div><b>{percent}% complete</b></Card>; })}
  </div></div>;
}
