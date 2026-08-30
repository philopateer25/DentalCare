import React, { useState } from 'react';
import { Layers, Activity } from 'lucide-react';
import Card from './Card';
import Badge from './Badge';

export default function OdontogramPreview() {
    const [selectedTooth, setSelectedTooth] = useState(16);

    const teethUpper = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];

    const toothDetails = {
        16: { condition: 'Composite Restoration', surface: 'MOD', status: 'Healthy', doctor: 'Dr. Sarah Vance' },
        24: { condition: 'Porcelain Crown', surface: 'Full', status: 'Completed', doctor: 'Dr. Alex Rivera' },
        11: { condition: 'Enamel Whitening', surface: 'Labial', status: 'Optimal', doctor: 'Dr. Clara Hughes' },
    };

    const currentInfo = toothDetails[selectedTooth] || {
        condition: 'Intact Natural Enamel',
        surface: 'Sound',
        status: 'Normal',
        doctor: 'Routine Exam',
    };

    return (
        <Card className="p-6 sm:p-8 space-y-6">
            <div className="flex items-center justify-between pb-4 border-b border-slate-800">
                <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-teal-500/10 border border-teal-500/30 flex items-center justify-center text-teal-400 font-bold">
                        <Activity className="w-5 h-5" />
                    </div>
                    <div>
                        <h3 className="text-sm font-bold text-white">Interactive Odontogram Chart</h3>
                        <p className="text-xs text-teal-400">Click any FDI tooth code below to inspect records</p>
                    </div>
                </div>
                <Badge variant="teal">Live FDI System</Badge>
            </div>

            {/* Tooth Grid */}
            <div className="space-y-2">
                <div className="flex justify-between text-xs font-semibold text-slate-400">
                    <span>Upper Maxillary Arch (FDI 11 - 28)</span>
                    <span className="text-teal-400 font-mono">Selected: Tooth #{selectedTooth}</span>
                </div>
                <div className="grid grid-cols-8 sm:grid-cols-16 gap-1.5 p-3 bg-slate-950/80 rounded-2xl border border-slate-800/80">
                    {teethUpper.map((num) => {
                        const isSelected = selectedTooth === num;
                        const hasRecord = !!toothDetails[num];
                        return (
                            <button
                                key={num}
                                type="button"
                                onClick={() => setSelectedTooth(num)}
                                className={`h-11 rounded-xl flex flex-col items-center justify-center text-[10px] font-bold transition-all ${
                                    isSelected
                                        ? 'bg-teal-500 text-slate-950 shadow-lg shadow-teal-500/30 scale-105'
                                        : hasRecord
                                        ? 'bg-teal-500/20 text-teal-300 border border-teal-500/40 hover:bg-teal-500/30'
                                        : 'bg-slate-900 text-slate-400 border border-slate-800 hover:border-slate-700 hover:text-white'
                                }`}
                            >
                                <span>{num}</span>
                                <span className={`w-1 h-1 rounded-full mt-0.5 ${hasRecord ? (isSelected ? 'bg-slate-950' : 'bg-teal-400') : 'bg-transparent'}`} />
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Selected Tooth Card */}
            <div className="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div className="space-y-1">
                    <div className="text-xs text-slate-400 font-medium">Tooth #{selectedTooth} Status</div>
                    <div className="text-sm font-bold text-white">{currentInfo.condition}</div>
                    <div className="text-xs text-slate-400">Surface: {currentInfo.surface} • Provider: {currentInfo.doctor}</div>
                </div>
                <Badge variant={currentInfo.status === 'Optimal' || currentInfo.status === 'Completed' ? 'emerald' : 'teal'}>
                    {currentInfo.status}
                </Badge>
            </div>
        </Card>
    );
}
