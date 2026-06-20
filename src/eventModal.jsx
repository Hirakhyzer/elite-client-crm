import { ACTIVITY_TYPES } from "./data";
import { Button, FormField, Modal } from "./components";

export function EventModal({ draft, onDraft, clients, onClose, onSave }) {
  const set = (key) => (event) => onDraft({ ...draft, [key]: event.target.value });
  return <Modal title="Schedule item" onClose={onClose}>
    <div className="form-stack">
      <FormField label="Title"><input value={draft.title} onChange={set("title")} /></FormField>
      <FormField label="Client"><select value={draft.client} onChange={set("client")}><option value="">No client selected</option>{clients.map((client) => <option key={client.id}>{client.name}</option>)}</select></FormField>
      <FormField label="Date"><input type="date" value={draft.date} onChange={set("date")} /></FormField>
      <FormField label="Time"><input type="time" value={draft.time} onChange={set("time")} /></FormField>
      <FormField label="Type"><select value={draft.type} onChange={set("type")}>{ACTIVITY_TYPES.map((item) => <option key={item}>{item}</option>)}</select></FormField>
    </div>
    <div className="modal-actions"><Button variant="outline" onClick={onClose}>Cancel</Button><Button onClick={onSave}>Add item</Button></div>
  </Modal>;
}
