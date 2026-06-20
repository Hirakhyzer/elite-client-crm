import { PIPELINE_STAGES, STATUSES, TIERS } from "./data";
import { Button, FormField, Modal } from "./components";

export function ClientModal({ title = "Client", draft, onDraft, onClose, onSave }) {
  const set = (key) => (event) => onDraft({ ...draft, [key]: event.target.value });
  return <Modal title={title} onClose={onClose} wide>
    <div className="form-grid">
      <FormField label="Client name"><input value={draft.name} onChange={set("name")} /></FormField>
      <FormField label="Company"><input value={draft.company} onChange={set("company")} /></FormField>
      <FormField label="Contact"><input value={draft.contact} onChange={set("contact")} /></FormField>
      <FormField label="Email"><input value={draft.email} onChange={set("email")} /></FormField>
      <FormField label="Project value"><input type="number" value={draft.deal} onChange={set("deal")} /></FormField>
      <FormField label="Lead score"><input type="range" min="0" max="100" value={draft.score} onChange={set("score")} /></FormField>
      <FormField label="Status"><select value={draft.status} onChange={set("status")}>{STATUSES.map((item) => <option key={item}>{item}</option>)}</select></FormField>
      <FormField label="Tier"><select value={draft.tier} onChange={set("tier")}>{TIERS.map((item) => <option key={item}>{item}</option>)}</select></FormField>
      <FormField label="Pipeline"><select value={draft.pipeline} onChange={set("pipeline")}>{PIPELINE_STAGES.map((item) => <option key={item}>{item}</option>)}</select></FormField>
      <FormField label="Notes" full><textarea rows="4" value={draft.notes} onChange={set("notes")} /></FormField>
    </div>
    <div className="modal-actions"><Button variant="outline" onClick={onClose}>Cancel</Button><Button onClick={onSave}>Save client</Button></div>
  </Modal>;
}
