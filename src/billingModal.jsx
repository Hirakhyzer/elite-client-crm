import { Button, FormField, Modal } from "./components";

export function BillingModal({ draft, onDraft, clients, onClose, onSave }) {
  const set = (key) => (event) => onDraft({ ...draft, [key]: event.target.value });
  return <Modal title="New billing record" onClose={onClose}>
    <div className="form-stack">
      <FormField label="Client"><select value={draft.client} onChange={set("client")}><option value="">Select client</option>{clients.map((client) => <option key={client.id}>{client.name}</option>)}</select></FormField>
      <FormField label="Amount"><input type="number" value={draft.amount} onChange={set("amount")} /></FormField>
      <FormField label="Due date"><input type="date" value={draft.due} onChange={set("due")} /></FormField>
      <FormField label="Status"><select value={draft.status} onChange={set("status")}><option>Pending</option><option>Paid</option><option>Overdue</option></select></FormField>
    </div>
    <div className="modal-actions"><Button variant="outline" onClick={onClose}>Cancel</Button><Button onClick={onSave}>Create record</Button></div>
  </Modal>;
}
