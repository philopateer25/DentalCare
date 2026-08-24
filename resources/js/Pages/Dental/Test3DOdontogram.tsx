import React, { useState } from 'react';
import axios from 'axios';
import Dental3DViewer from '../../Components/Dental3D/Dental3DViewer';
import { SingleToothViewer } from '../../Components/Dental3D/SingleToothViewer';
import {
  CONDITION_COLORS,
  CONDITION_LABELS,
  PatientInfo,
  ToothRecord,
  SurfaceCondition,
} from '../../Components/Dental3D/types';

interface Props {
  patient: PatientInfo;
  initialRecords?: Record<string, ToothRecord>;
}

// FDI Teeth list in anatomical order
const FDI_TEETH = {
  upperRight: ['18', '17', '16', '15', '14', '13', '12', '11'],
  upperLeft: ['21', '22', '23', '24', '25', '26', '27', '28'],
  lowerRight: ['48', '47', '46', '45', '44', '43', '42', '41'],
  lowerLeft: ['31', '32', '33', '34', '35', '36', '37', '38'],
};

export const Test3DOdontogram: React.FC<Props> = ({ patient, initialRecords = {} }) => {
  const [teethRecords, setTeethRecords] = useState<Record<string, ToothRecord>>(initialRecords);
  const [selectedTooth, setSelectedTooth] = useState<string | null>('11');
  const [savingStatus, setSavingStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');

  const currentRecord = selectedTooth ? teethRecords[selectedTooth] : undefined;
  const currentCondition: ToothCondition = currentRecord?.condition || 'healthy';
  const currentNote = currentRecord?.notes || '';
  const currentSurfaces = currentRecord?.surfaces || {};

  const handleConditionSelect = async (condition: ToothCondition) => {
    if (!selectedTooth) return;

    // Optimistic UI Update
    const previousState = { ...teethRecords };
    const updatedRecord = {
      ...currentRecord,
      condition: condition,
    };
    
    setTeethRecords((prev) => ({
      ...prev,
      [selectedTooth]: updatedRecord,
    }));

    setSavingStatus('saving');

    try {
      await axios.post(`/api/patients/${patient.id}/teeth`, {
        tooth_number: selectedTooth,
        condition: condition,
        notes: currentNote,
        surfaces: currentSurfaces,
      });
      setSavingStatus('saved');
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to update tooth condition:', err);
      setTeethRecords(previousState); // Rollback on error
      setSavingStatus('error');
    }
  };

  const handleNoteSave = async (newNote: string) => {
    if (!selectedTooth) return;

    const updatedRecord = { ...currentRecord, condition: currentCondition, notes: newNote };
    setTeethRecords((prev) => ({
      ...prev,
      [selectedTooth]: updatedRecord,
    }));
    
    setSavingStatus('saving');

    try {
      await axios.post(`/api/patients/${patient.id}/teeth`, {
        tooth_number: selectedTooth,
        condition: currentCondition,
        notes: newNote,
        surfaces: currentSurfaces,
      });
      setSavingStatus('saved');
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to update tooth note:', err);
      setSavingStatus('error');
    }
  };

  const handleSurfaceSelect = async (surface: keyof ToothSurfaces, condition: SurfaceCondition) => {
    if (!selectedTooth) return;

    // Toggle logic: if clicking the same condition, clear it (healthy)
    const currentSurfaceCondition = currentSurfaces[surface];
    const newCondition = currentSurfaceCondition === condition ? 'healthy' : condition;

    const newSurfaces = { ...currentSurfaces, [surface]: newCondition };
    const updatedRecord = { ...currentRecord, condition: currentCondition, surfaces: newSurfaces };

    setTeethRecords((prev) => ({
      ...prev,
      [selectedTooth]: updatedRecord,
    }));
    
    setSavingStatus('saving');

    try {
      await axios.post(`/api/patients/${patient.id}/teeth`, {
        tooth_number: selectedTooth,
        condition: currentCondition,
        notes: currentNote,
        surfaces: newSurfaces,
      });
      setSavingStatus('saved');
      setTimeout(() => setSavingStatus('idle'), 2000);
    } catch (err) {
      console.error('Failed to update tooth surface:', err);
      setSavingStatus('error');
    }
  };

  // Double-Click Modal State
  const [modalTooth, setModalTooth] = useState<string | null>(null);

  // Quick statistics
  const conditionCounts = Object.values(teethRecords).reduce((acc, record) => {
    const cond = record.condition || 'healthy';
    acc[cond] = (acc[cond] || 0) + 1;
    return acc;
  }, {} as Record<ToothCondition, number>);

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 font-sans p-4 md:p-8">
      {/* Top Header Bar */}
      <div className="max-w-7xl mx-auto mb-6 bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-3">
            <span className="px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold rounded-full uppercase tracking-wider">
              3D Interactive Odontogram
            </span>
            <span className="text-xs text-slate-400">
              Patient File: <strong className="text-slate-200">{patient.file_number}</strong>
            </span>
          </div>
          <h1 className="text-2xl md:text-3xl font-bold mt-1 text-white tracking-tight">
            {patient.full_name}
          </h1>
        </div>

        {/* Sync Status Badge */}
        <div className="flex items-center gap-3 bg-slate-950/60 px-4 py-2 rounded-xl border border-slate-800 text-sm">
          {savingStatus === 'saving' && (
            <div className="flex items-center gap-2 text-amber-400">
              <span className="w-2 h-2 rounded-full bg-amber-400 animate-ping" />
              <span>Saving changes...</span>
            </div>
          )}
          {savingStatus === 'saved' && (
            <div className="flex items-center gap-2 text-emerald-400">
              <span className="w-2 h-2 rounded-full bg-emerald-400" />
              <span>Saved to database</span>
            </div>
          )}
          {savingStatus === 'error' && (
            <div className="flex items-center gap-2 text-red-400">
              <span className="w-2 h-2 rounded-full bg-red-400" />
              <span>Save failed. Reverted.</span>
            </div>
          )}
          {savingStatus === 'idle' && (
            <div className="flex items-center gap-2 text-slate-400">
              <span className="w-2 h-2 rounded-full bg-slate-500" />
              <span>Live Synced</span>
            </div>
          )}
        </div>
      </div>

      {/* Main Layout Grid */}
      <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left Side: 3D Viewport & Tooth Quick Selector (7 Cols) */}
        <div className="lg:col-span-7 flex flex-col gap-6">
          <div className="relative h-[500px] w-full">
            <Dental3DViewer
              teethRecords={teethRecords}
              selectedTooth={selectedTooth}
              onSelectTooth={(num) => setSelectedTooth(num)}
              onDoubleClickTooth={(num) => setModalTooth(num)}
            />
          </div>

          {/* Quick FDI Teeth Selector Grid */}
          <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-xl">
            <h3 className="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-3">
              FDI Dental Arch Quick Selector
            </h3>

            {/* Upper Quadrants */}
            <div className="mb-4">
              <div className="text-[11px] font-medium text-slate-400 mb-1.5 flex justify-between">
                <span>Upper Right (Q1)</span>
                <span>Upper Left (Q2)</span>
              </div>
              <div className="grid grid-cols-16 gap-1 sm:gap-1.5 bg-slate-950/80 p-2 rounded-xl border border-slate-800/80">
                {[...FDI_TEETH.upperRight, ...FDI_TEETH.upperLeft].map((tNum) => {
                  const cond = teethRecords[tNum]?.condition || 'healthy';
                  const isSel = selectedTooth === tNum;
                  return (
                    <button
                      key={tNum}
                      onClick={() => setSelectedTooth(tNum)}
                      style={{
                        borderColor: isSel ? '#10B981' : CONDITION_COLORS[cond],
                      }}
                      className={`h-9 flex flex-col items-center justify-center rounded-lg text-xs font-semibold transition-all border ${
                        isSel
                          ? 'bg-emerald-500/20 text-emerald-300 shadow-md ring-2 ring-emerald-500/50'
                          : 'bg-slate-900 text-slate-300 hover:bg-slate-800'
                      }`}
                    >
                      <span>{tNum}</span>
                      <span
                        className="w-1.5 h-1.5 rounded-full mt-0.5"
                        style={{ backgroundColor: CONDITION_COLORS[cond] }}
                      />
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Lower Quadrants */}
            <div>
              <div className="text-[11px] font-medium text-slate-400 mb-1.5 flex justify-between">
                <span>Lower Right (Q4)</span>
                <span>Lower Left (Q3)</span>
              </div>
              <div className="grid grid-cols-16 gap-1 sm:gap-1.5 bg-slate-950/80 p-2 rounded-xl border border-slate-800/80">
                {[...FDI_TEETH.lowerRight, ...FDI_TEETH.lowerLeft].map((tNum) => {
                  const cond = teethRecords[tNum]?.condition || 'healthy';
                  const isSel = selectedTooth === tNum;
                  return (
                    <button
                      key={tNum}
                      onClick={() => setSelectedTooth(tNum)}
                      style={{
                        borderColor: isSel ? '#10B981' : CONDITION_COLORS[cond],
                      }}
                      className={`h-9 flex flex-col items-center justify-center rounded-lg text-xs font-semibold transition-all border ${
                        isSel
                          ? 'bg-emerald-500/20 text-emerald-300 shadow-md ring-2 ring-emerald-500/50'
                          : 'bg-slate-900 text-slate-300 hover:bg-slate-800'
                      }`}
                    >
                      <span>{tNum}</span>
                      <span
                        className="w-1.5 h-1.5 rounded-full mt-0.5"
                        style={{ backgroundColor: CONDITION_COLORS[cond] }}
                      />
                    </button>
                  );
                })}
              </div>
            </div>
          </div>
        </div>

        {/* Right Side: Clinical Condition Selector Panel (5 Cols) */}
        <div className="lg:col-span-5 flex flex-col gap-6">
          {/* Selected Tooth Info Card */}
          <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div className="flex items-center justify-between pb-4 border-b border-slate-800">
              <div>
                <div className="text-xs font-semibold uppercase text-slate-400">
                  Selected Tooth
                </div>
                <div className="text-3xl font-extrabold text-white mt-0.5">
                  {selectedTooth ? `Tooth #${selectedTooth}` : 'None Selected'}
                </div>
              </div>
              {selectedTooth && (
                <div
                  className="px-3.5 py-1.5 rounded-xl border text-xs font-bold flex items-center gap-2"
                  style={{
                    backgroundColor: `${CONDITION_COLORS[currentCondition]}20`,
                    borderColor: CONDITION_COLORS[currentCondition],
                    color: CONDITION_COLORS[currentCondition],
                  }}
                >
                  <span
                    className="w-2.5 h-2.5 rounded-full"
                    style={{ backgroundColor: CONDITION_COLORS[currentCondition] }}
                  />
                  {CONDITION_LABELS[currentCondition].label}
                </div>
              )}
            </div>

            {/* Condition Selector Action Buttons */}
            <div className="mt-5">
              <label className="block text-xs font-semibold uppercase text-slate-400 mb-3">
                Apply Clinical Condition (1-Click)
              </label>

              <div className="grid grid-cols-1 gap-2.5">
                {(Object.keys(CONDITION_LABELS) as ToothCondition[]).map((condKey) => {
                  const meta = CONDITION_LABELS[condKey];
                  const isCurrent = currentCondition === condKey;
                  const hexColor = CONDITION_COLORS[condKey];

                  return (
                    <button
                      key={condKey}
                      onClick={() => handleConditionSelect(condKey)}
                      disabled={!selectedTooth}
                      className={`w-full flex items-center justify-between p-3 rounded-xl border text-left transition-all ${
                        isCurrent
                          ? 'bg-slate-800 border-slate-600 shadow-md ring-1 ring-slate-500'
                          : 'bg-slate-950/60 border-slate-800/80 hover:bg-slate-850 hover:border-slate-700'
                      } ${!selectedTooth ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
                    >
                      <div className="flex items-center gap-3">
                        <div
                          className="w-4 h-4 rounded-full border border-slate-700 flex-shrink-0 shadow-inner"
                          style={{ backgroundColor: hexColor }}
                        />
                        <div>
                          <div className="text-sm font-semibold text-slate-200">
                            {meta.label}
                          </div>
                          <div className="text-xs text-slate-400">
                            {meta.description}
                          </div>
                        </div>
                      </div>

                      {isCurrent && (
                        <span className="text-xs font-bold px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                          Active
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
              
              {currentCondition === 'custom' && selectedTooth && (
                <div className="mt-4 p-4 bg-slate-950/60 border border-rose-500/30 rounded-xl">
                  <label className="block text-xs font-semibold uppercase text-slate-400 mb-2">
                    Custom Note for Tooth #{selectedTooth}
                  </label>
                  <textarea
                    className="w-full bg-slate-900 border border-slate-700 rounded-lg p-3 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-rose-500/50 focus:ring-1 focus:ring-rose-500/50 transition-all resize-none"
                    rows={3}
                    placeholder="Type custom doctor notes here... (e.g. Needs specialized cleaning)"
                    value={currentNote}
                    onChange={(e) => {
                      const updatedRecord = { ...currentRecord, condition: currentCondition, notes: e.target.value };
                      setTeethRecords(prev => ({
                        ...prev,
                        [selectedTooth]: updatedRecord
                      }));
                    }}
                    onBlur={(e) => handleNoteSave(e.target.value)}
                  />
                  <div className="text-right mt-2 text-[10px] text-slate-500">
                    Saves automatically on click away
                  </div>
                </div>
              )}

              {/* Surface Details (M-O-D-B-L) */}
              {selectedTooth && ['healthy', 'active_caries', 'composite_filled'].includes(currentCondition) && (
                <div className="mt-5 bg-slate-950/60 border border-slate-800/80 rounded-xl p-4">
                  <label className="block text-xs font-semibold uppercase text-slate-400 mb-3">
                    Surface Details (M-O-D-B-L)
                  </label>
                  <div className="flex justify-between gap-2">
                    {(['mesial', 'occlusal', 'distal', 'buccal', 'lingual'] as const).map((surface) => {
                      const surfCond = currentSurfaces[surface] || 'healthy';
                      const label = surface.charAt(0).toUpperCase();
                      const isActive = surfCond !== 'healthy';
                      return (
                        <button
                          key={surface}
                          onClick={() => {
                            // Cycle through conditions: healthy -> active_caries -> composite_filled -> healthy
                            let nextCond: SurfaceCondition = 'active_caries';
                            if (surfCond === 'active_caries') nextCond = 'composite_filled';
                            if (surfCond === 'composite_filled') nextCond = 'healthy';
                            handleSurfaceSelect(surface, nextCond);
                          }}
                          className={`w-10 h-10 rounded-lg flex items-center justify-center text-sm font-bold border transition-all ${
                            isActive 
                              ? `bg-slate-800 text-white shadow-md border-slate-600` 
                              : `bg-slate-900 text-slate-500 border-slate-800 hover:bg-slate-800 hover:text-slate-300`
                          }`}
                          style={{
                            borderColor: isActive ? CONDITION_COLORS[surfCond] : undefined,
                            boxShadow: isActive ? `0 0 8px ${CONDITION_COLORS[surfCond]}40` : undefined,
                          }}
                          title={surface}
                        >
                          {label}
                        </button>
                      );
                    })}
                  </div>
                  <div className="flex items-center gap-3 mt-3 text-[10px] text-slate-500 justify-center">
                    <span className="flex items-center gap-1"><span className="w-1.5 h-1.5 rounded-full bg-[#E8E8E8]" /> Healthy</span>
                    <span className="flex items-center gap-1"><span className="w-1.5 h-1.5 rounded-full bg-[#DC2626]" /> Caries</span>
                    <span className="flex items-center gap-1"><span className="w-1.5 h-1.5 rounded-full bg-[#3B82F6]" /> Filled</span>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Condition Overview Summary Card */}
          <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h3 className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4">
              Odontogram Summary Statistics
            </h3>
            <div className="grid grid-cols-2 gap-3">
              {(Object.keys(CONDITION_LABELS) as ToothCondition[]).map((condKey) => {
                const count = conditionCounts[condKey] || 0;
                const meta = CONDITION_LABELS[condKey];
                const hexColor = CONDITION_COLORS[condKey];
                return (
                  <div
                    key={condKey}
                    className="flex items-center justify-between p-2.5 bg-slate-950/70 border border-slate-800/80 rounded-xl"
                  >
                    <div className="flex items-center gap-2">
                      <span
                        className="w-2.5 h-2.5 rounded-full"
                        style={{ backgroundColor: hexColor }}
                      />
                      <span className="text-xs text-slate-300 font-medium">
                        {meta.label}
                      </span>
                    </div>
                    <span className="text-xs font-bold text-white px-2 py-0.5 bg-slate-800 rounded-md">
                      {count}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>

      {/* Double-Click Details Modal */}
      {modalTooth && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm transition-opacity">
          <div className="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div className="p-5 border-b border-slate-800 flex justify-between items-center bg-slate-950/50">
              <h2 className="text-lg font-bold text-white">Tooth #{modalTooth} Details</h2>
              <button 
                onClick={() => setModalTooth(null)}
                className="text-slate-400 hover:text-white transition-colors"
              >
                ✕
              </button>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-0 border-b border-slate-800">
              {/* Left Column: Isolated 3D View */}
              <div className="bg-slate-950/40 p-4 border-b md:border-b-0 md:border-r border-slate-800">
                <SingleToothViewer 
                  modelUrl="/models/teeth-seperated.glb" 
                  toothNumber={modalTooth} 
                  record={teethRecords[modalTooth]} 
                />
              </div>

              {/* Right Column: Diagnostic Info */}
              <div className="p-6 flex flex-col gap-5 bg-slate-900/50">
                {/* Main Condition */}
                <div>
                  <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-2">Main Condition</label>
                  <div className="flex items-center gap-2">
                    <span
                      className="w-3 h-3 rounded-full"
                      style={{ backgroundColor: CONDITION_COLORS[teethRecords[modalTooth]?.condition || 'healthy'] }}
                    />
                    <span className="font-medium text-slate-200">
                      {CONDITION_LABELS[teethRecords[modalTooth]?.condition || 'healthy'].label}
                    </span>
                  </div>
                </div>

              {/* Surface Breakdown */}
              <div className="bg-slate-950/50 p-4 rounded-xl border border-slate-800/80">
                <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-3">Surface Breakdown</label>
                <div className="grid grid-cols-2 gap-y-3 gap-x-6">
                  {(['mesial', 'occlusal', 'distal', 'buccal', 'lingual'] as const).map((surface) => {
                    const sCond = teethRecords[modalTooth]?.surfaces?.[surface] || 'healthy';
                    const cLabel = sCond === 'healthy' ? 'Healthy' : sCond === 'active_caries' ? 'Active Caries' : 'Composite Filled';
                    return (
                      <div key={surface} className="flex justify-between items-center text-sm">
                        <span className="text-slate-400 capitalize">{surface}:</span>
                        <div className="flex items-center gap-1.5">
                          <span
                            className="w-2 h-2 rounded-full"
                            style={{ backgroundColor: CONDITION_COLORS[sCond] }}
                          />
                          <span className="text-slate-200">{cLabel}</span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>

              {/* Clinical Notes */}
              {teethRecords[modalTooth]?.notes && (
                <div>
                  <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-2">Clinical Notes</label>
                  <div className="bg-slate-800/50 border border-slate-700/50 rounded-lg p-3 text-sm text-slate-300 italic whitespace-pre-wrap">
                    "{teethRecords[modalTooth]?.notes}"
                  </div>
                </div>
              )}
            </div>
            </div>

            <div className="p-4 border-t border-slate-800 bg-slate-950/50 flex justify-end">
              <button 
                onClick={() => setModalTooth(null)}
                className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-lg transition-colors"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Test3DOdontogram;
