import { Button, FormField, Modal } from "./components";

export function EmailModal({ draft, onDraft, clients, onClose, onSave }) {
  const set = (key) => (event) => onDraft({ ...draft, [key]: event.target.value });
  return <Modal title="Compose email" onClose={onClose}>
    <div className="form-stack">
      <FormField label="To"><select value={draft.to} onChange={set("to")}><option value="">Select a client email</option>{clients.map((client) => <option key={client.id} value={client.email}>{client.name} · {client.email}</option>)}</select></FormField>
      <FormField label="Subject"><input value={draft.subject} onChange={set("subject")} /></FormField>
      <FormField label="Message"><textarea rows="7" value={draft.body} onChange={set("body")} placeholder="Write your client message..." /></FormField>
    </div>
    <div className="modal-actions"><Button variant="outline" onClick={onClose}>Discard</Button><Button onClick={onSave}>Save email</Button></div>
  </Modal>;
}
