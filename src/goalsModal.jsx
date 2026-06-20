import { useState } from "react";
import { Button, FormField, Modal } from "./components";

export function GoalsModal({ goals, onClose, onSave }) {
  const [draft, setDraft] = useState(goals);
  const update = (index, key, value) => setDraft(draft.map((goal, i) => i === index ? { ...goal, [key]: Number(value) } : goal));
  return <Modal title="Edit goals" onClose={onClose}>
    {draft.map((goal, index) => <div className="goal-edit" key={goal.label}>
      <strong>{goal.label}</strong>
      <div>
        <FormField label="Current"><input type="number" value={goal.current} onChange={(event) => update(index, "current", event.target.value)} /></FormField>
        <FormField label="Target"><input type="number" value={goal.target} onChange={(event) => update(index, "target", event.target.value)} /></FormField>
      </div>
    </div>)}
    <div className="modal-actions"><Button variant="outline" onClick={onClose}>Cancel</Button><Button onClick={() => onSave(draft)}>Save goals</Button></div>
  </Modal>;
}
