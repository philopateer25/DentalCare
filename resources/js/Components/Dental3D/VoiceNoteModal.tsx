import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Mic, Square, Play, Pause, RotateCcw, Send, Download, CheckCircle, AlertCircle, FileAudio, ExternalLink, X } from 'lucide-react';

interface ProcedureInfo {
  id?: number;
  tooth_number_fdi?: number | string;
  procedure_code?: { title?: string; code?: string };
  anesthetic_agent?: string;
  anesthetic_dosage?: string;
  anesthetic_technique?: string;
  materials_used_json?: string[] | string;
  tools_used_json?: string[] | string;
}

interface VoiceNoteModalProps {
  isOpen: boolean;
  onClose: () => void;
  patient: {
    id: number;
    first_name: string;
    last_name: string;
    phone?: string;
    whatsapp_number?: string;
  };
  toothNumber?: string | null;
  procedure?: ProcedureInfo | null;
  onSuccess?: () => void;
}

const TEMPLATES = [
  {
    key: 'extraction_care',
    label: 'Post-Extraction Care',
    desc: 'Bite gauze 45 min, no spitting/straws, cold compress for swelling.',
    file: '/audio/templates/extraction_care.wav',
  },
  {
    key: 'implant_care',
    label: 'Dental Implant Aftercare',
    desc: 'Soft diet 48h, chlorhexidine rinse, strictly avoid smoking.',
    file: '/audio/templates/implant_care.wav',
  },
  {
    key: 'root_canal_care',
    label: 'Root Canal Therapy Aftercare',
    desc: 'Chew on opposite side, mild soreness normal, analgesics as prescribed.',
    file: '/audio/templates/root_canal_care.wav',
  },
  {
    key: 'composite_care',
    label: 'Composite Restoration Care',
    desc: 'Avoid chewing until local numbness subsides completely.',
    file: '/audio/templates/composite_care.wav',
  },
];

export const VoiceNoteModal: React.FC<VoiceNoteModalProps> = ({
  isOpen,
  onClose,
  patient,
  toothNumber,
  procedure,
  onSuccess,
}) => {
  const [mode, setMode] = useState<'record' | 'template'>('record');
  const [selectedTemplate, setSelectedTemplate] = useState<string>('extraction_care');
  const [templateAudioPlaying, setTemplateAudioPlaying] = useState<boolean>(false);
  const templateAudioRef = useRef<HTMLAudioElement | null>(null);

  // Recording states
  const [isRecording, setIsRecording] = useState(false);
  const [recordingSeconds, setRecordingSeconds] = useState(0);
  const [recordedBlob, setRecordedBlob] = useState<Blob | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [isPlayingPreview, setIsPlayingPreview] = useState(false);
  const [permissionError, setPermissionError] = useState<string | null>(null);

  // Network & WhatsApp result states
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [resultData, setResultData] = useState<{
    share_url: string;
    download_url: string;
    whatsapp_web_url: string;
    whatsapp_mobile_url: string;
    normalized_phone: string;
    message_text: string;
  } | null>(null);

  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const audioChunksRef = useRef<Blob[]>([]);
  const timerIntervalRef = useRef<any>(null);
  const mediaStreamRef = useRef<MediaStream | null>(null);
  const previewAudioRef = useRef<HTMLAudioElement | null>(null);

  // Clean up on modal close or unmount
  useEffect(() => {
    return () => {
      stopAndCleanupStream();
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      if (templateAudioRef.current) templateAudioRef.current.pause();
    };
  }, []);

  const stopAndCleanupStream = () => {
    if (mediaStreamRef.current) {
      mediaStreamRef.current.getTracks().forEach((track) => track.stop());
      mediaStreamRef.current = null;
    }
    if (timerIntervalRef.current) {
      clearInterval(timerIntervalRef.current);
      timerIntervalRef.current = null;
    }
  };

  const startRecording = async () => {
    setPermissionError(null);
    setSubmitError(null);
    setRecordedBlob(null);
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    setPreviewUrl(null);

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setPermissionError('Microphone audio recording is not supported in this browser.');
      return;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      mediaStreamRef.current = stream;

      // Runtime format detection
      const preferredMimes = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
        'audio/mp4',
        'audio/wav',
      ];
      const mimeType = preferredMimes.find((m) => MediaRecorder.isTypeSupported(m)) || '';

      const recorder = mimeType
        ? new MediaRecorder(stream, { mimeType })
        : new MediaRecorder(stream);

      mediaRecorderRef.current = recorder;
      audioChunksRef.current = [];

      recorder.ondataavailable = (event) => {
        if (event.data && event.data.size > 0) {
          audioChunksRef.current.push(event.data);
        }
      };

      recorder.onstop = () => {
        const finalType = recorder.mimeType || 'audio/webm';
        const blob = new Blob(audioChunksRef.current, { type: finalType });
        setRecordedBlob(blob);
        const url = URL.createObjectURL(blob);
        setPreviewUrl(url);
        stopAndCleanupStream();
      };

      recorder.start(250); // collect chunks every 250ms
      setIsRecording(true);
      setRecordingSeconds(0);

      timerIntervalRef.current = setInterval(() => {
        setRecordingSeconds((prev) => {
          if (prev >= 59) {
            stopRecording();
            return 60;
          }
          return prev + 1;
        });
      }, 1000);
    } catch (err: any) {
      console.error('Microphone access failed:', err);
      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        setPermissionError('Microphone permission was denied. Please allow microphone access in your browser settings.');
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        setPermissionError('No microphone hardware was detected on your device.');
      } else {
        setPermissionError(err.message || 'Failed to initialize microphone recording.');
      }
      stopAndCleanupStream();
    }
  };

  const stopRecording = () => {
    if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
      mediaRecorderRef.current.stop();
    }
    setIsRecording(false);
    if (timerIntervalRef.current) {
      clearInterval(timerIntervalRef.current);
      timerIntervalRef.current = null;
    }
  };

  const resetRecording = () => {
    stopAndCleanupStream();
    setIsRecording(false);
    setRecordingSeconds(0);
    setRecordedBlob(null);
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    setPreviewUrl(null);
    setIsPlayingPreview(false);
    setResultData(null);
    setSubmitError(null);
  };

  const togglePreviewPlayback = () => {
    if (!previewAudioRef.current) return;
    if (isPlayingPreview) {
      previewAudioRef.current.pause();
      setIsPlayingPreview(false);
    } else {
      previewAudioRef.current.play();
      setIsPlayingPreview(true);
    }
  };

  const handleTemplatePreview = (templateKey: string) => {
    const t = TEMPLATES.find((item) => item.key === templateKey);
    if (!t) return;

    if (templateAudioRef.current) {
      templateAudioRef.current.pause();
    }

    if (selectedTemplate === templateKey && templateAudioPlaying) {
      setTemplateAudioPlaying(false);
      return;
    }

    const audio = new Audio(t.file);
    templateAudioRef.current = audio;
    audio.onended = () => setTemplateAudioPlaying(false);
    audio.play();
    setSelectedTemplate(templateKey);
    setTemplateAudioPlaying(true);
  };

  const handleSubmit = async () => {
    setIsSubmitting(true);
    setSubmitError(null);

    const formData = new FormData();
    if (toothNumber) {
      formData.append('tooth_number_fdi', toothNumber);
    }
    if (procedure?.id) {
      formData.append('treatment_procedure_id', String(procedure.id));
    }

    if (mode === 'record') {
      if (!recordedBlob) {
        setSubmitError('Please record your voice instructions first.');
        setIsSubmitting(false);
        return;
      }
      const extension = recordedBlob.type.includes('wav') ? 'wav' : 'webm';
      formData.append('audio', recordedBlob, `voice_note_${patient.id}.${extension}`);
      formData.append('duration_seconds', String(recordingSeconds));
    } else {
      formData.append('template', selectedTemplate);
    }

    try {
      const res = await axios.post(`/api/patients/${patient.id}/voice-notes`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      setResultData({
        share_url: res.data.share_url,
        download_url: res.data.download_url,
        whatsapp_web_url: res.data.whatsapp_web_url,
        whatsapp_mobile_url: res.data.whatsapp_mobile_url,
        normalized_phone: res.data.normalized_phone,
        message_text: res.data.message_text,
      });

      onSuccess?.();
    } catch (err: any) {
      console.error('Voice note upload error', err);
      setSubmitError(
        err.response?.data?.message || 'Failed to save voice note. Please check server logs.'
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-in fade-in duration-200">
      <div className="bg-slate-900 border border-slate-700/80 rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        {/* Modal Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-slate-950/60">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
              <Mic className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-base font-bold text-white flex items-center gap-2">
                Post-Op WhatsApp Voice Note
                {toothNumber && (
                  <span className="text-xs px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-medium">
                    Tooth #{toothNumber}
                  </span>
                )}
              </h2>
              <p className="text-xs text-slate-400">
                Patient: <span className="text-slate-200 font-medium">{patient.first_name} {patient.last_name}</span>
                {patient.phone && <span className="ml-2 font-mono text-slate-400">({patient.phone})</span>}
              </p>
            </div>
          </div>
          <button
            onClick={() => {
              stopAndCleanupStream();
              onClose();
            }}
            className="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-6 overflow-y-auto flex flex-col gap-5 flex-1">
          {/* Procedure details banner if available */}
          {procedure && (
            <div className="bg-slate-950/70 border border-slate-800 rounded-xl p-3.5 flex flex-col gap-1.5 text-xs">
              <div className="text-slate-400 font-medium">Associated Clinical Procedure:</div>
              <div className="text-slate-200 font-semibold text-sm">
                {procedure.procedure_code?.title || 'Dental Procedure'}
              </div>
              {procedure.anesthetic_agent && (
                <div className="text-emerald-400 flex items-center gap-1.5 mt-0.5">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-400" />
                  <span>Anesthesia: {procedure.anesthetic_agent} ({procedure.anesthetic_dosage || '1 carpule'}) - {procedure.anesthetic_technique || 'Infiltration'}</span>
                </div>
              )}
            </div>
          )}

          {/* Mode Selector Tabs */}
          {!resultData && (
            <div className="grid grid-cols-2 gap-2 p-1 bg-slate-950/60 rounded-xl border border-slate-800">
              <button
                type="button"
                onClick={() => setMode('record')}
                className={`py-2 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-2 ${
                  mode === 'record'
                    ? 'bg-emerald-600 text-white shadow-lg'
                    : 'text-slate-400 hover:text-slate-200'
                }`}
              >
                <Mic className="w-3.5 h-3.5" />
                Live In-Browser Recording
              </button>
              <button
                type="button"
                onClick={() => setMode('template')}
                className={`py-2 text-xs font-semibold rounded-lg transition-all flex items-center justify-center gap-2 ${
                  mode === 'template'
                    ? 'bg-emerald-600 text-white shadow-lg'
                    : 'text-slate-400 hover:text-slate-200'
                }`}
              >
                <FileAudio className="w-3.5 h-3.5" />
                Pre-Recorded Templates
              </button>
            </div>
          )}

          {/* MODE 1: LIVE RECORDING */}
          {!resultData && mode === 'record' && (
            <div className="flex flex-col items-center justify-center py-4 bg-slate-950/40 rounded-2xl border border-slate-800 p-5">
              {permissionError && (
                <div className="w-full mb-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-start gap-2">
                  <AlertCircle className="w-4 h-4 mt-0.5 shrink-0" />
                  <span>{permissionError}</span>
                </div>
              )}

              {/* Timer & Pulsing Ring */}
              <div className="relative flex items-center justify-center my-3">
                <div
                  className={`w-28 h-28 rounded-full flex flex-col items-center justify-center border-2 transition-all duration-300 ${
                    isRecording
                      ? 'border-rose-500 bg-rose-950/30 scale-105 shadow-xl shadow-rose-950/50 animate-pulse'
                      : recordedBlob
                      ? 'border-emerald-500 bg-emerald-950/20'
                      : 'border-slate-700 bg-slate-900/60'
                  }`}
                >
                  <span className="font-mono text-2xl font-bold text-white tracking-wider">
                    {String(Math.floor(recordingSeconds / 60)).padStart(2, '0')}:
                    {String(recordingSeconds % 60).padStart(2, '0')}
                  </span>
                  <span className="text-[10px] uppercase font-semibold text-slate-400 tracking-wider mt-0.5">
                    {isRecording ? 'Recording' : recordedBlob ? 'Recorded' : 'Max 60s'}
                  </span>
                </div>
              </div>

              {/* Recording Controls */}
              <div className="flex items-center gap-3 mt-4">
                {!isRecording && !recordedBlob && (
                  <button
                    type="button"
                    onClick={startRecording}
                    className="flex items-center gap-2 px-5 py-2.5 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl shadow-lg transition-transform active:scale-95"
                  >
                    <Mic className="w-4 h-4" />
                    Start Recording
                  </button>
                )}

                {isRecording && (
                  <button
                    type="button"
                    onClick={stopRecording}
                    className="flex items-center gap-2 px-5 py-2.5 bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-xl shadow-lg transition-transform active:scale-95 animate-pulse"
                  >
                    <Square className="w-4 h-4 fill-white" />
                    Stop Recording
                  </button>
                )}

                {recordedBlob && (
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={togglePreviewPlayback}
                      className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-lg transition-all"
                    >
                      {isPlayingPreview ? (
                        <>
                          <Pause className="w-4 h-4" /> Pause Preview
                        </>
                      ) : (
                        <>
                          <Play className="w-4 h-4 fill-white" /> Play Preview
                        </>
                      )}
                    </button>
                    <button
                      type="button"
                      onClick={resetRecording}
                      className="p-2 text-slate-400 hover:text-white rounded-xl bg-slate-800 hover:bg-slate-700 transition-colors"
                      title="Re-record"
                    >
                      <RotateCcw className="w-4 h-4" />
                    </button>
                  </div>
                )}
              </div>

              {/* Hidden preview audio element */}
              {previewUrl && (
                <audio
                  ref={previewAudioRef}
                  src={previewUrl}
                  onEnded={() => setIsPlayingPreview(false)}
                  className="hidden"
                />
              )}
            </div>
          )}

          {/* MODE 2: CLINICAL AUDIO TEMPLATES */}
          {!resultData && mode === 'template' && (
            <div className="flex flex-col gap-2.5">
              <label className="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                Select Pre-Recorded Post-Op Instruction:
              </label>
              <div className="grid grid-cols-1 gap-2.5">
                {TEMPLATES.map((tmpl) => {
                  const isSelected = selectedTemplate === tmpl.key;
                  return (
                    <div
                      key={tmpl.key}
                      onClick={() => setSelectedTemplate(tmpl.key)}
                      className={`p-3.5 rounded-xl border text-left cursor-pointer transition-all flex items-center justify-between ${
                        isSelected
                          ? 'border-emerald-500/80 bg-emerald-950/20 shadow-md'
                          : 'border-slate-800 bg-slate-950/40 hover:border-slate-700'
                      }`}
                    >
                      <div className="flex flex-col gap-1 pr-3">
                        <div className="flex items-center gap-2">
                          <span className={`w-2 h-2 rounded-full ${isSelected ? 'bg-emerald-400' : 'bg-slate-600'}`} />
                          <span className="text-xs font-bold text-slate-100">{tmpl.label}</span>
                        </div>
                        <p className="text-[11px] text-slate-400">{tmpl.desc}</p>
                      </div>

                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleTemplatePreview(tmpl.key);
                        }}
                        className="shrink-0 p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition-colors"
                        title="Listen to template preview"
                      >
                        {isSelected && templateAudioPlaying ? (
                          <Pause className="w-3.5 h-3.5 text-emerald-400" />
                        ) : (
                          <Play className="w-3.5 h-3.5" />
                        )}
                      </button>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* ERROR ALERT */}
          {submitError && (
            <div className="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span>{submitError}</span>
            </div>
          )}

          {/* STEP 3: RESULT & WHATSAPP ACTIONS (SUCCESS) */}
          {resultData && (
            <div className="flex flex-col gap-4 animate-in fade-in duration-300">
              <div className="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3">
                <CheckCircle className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
                <div className="flex flex-col gap-1">
                  <h4 className="text-xs font-bold text-emerald-300">Voice Note Secured & Ready to Send</h4>
                  <p className="text-[11px] text-slate-300">
                    Audio instructions have been securely tokenized (14-day expiry). Prepared for phone:{' '}
                    <span className="font-mono font-bold text-white">+{resultData.normalized_phone || patient.phone}</span>
                  </p>
                </div>
              </div>

              {/* Message Preview Box */}
              <div className="bg-slate-950/80 border border-slate-800 rounded-xl p-3.5 flex flex-col gap-1.5">
                <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                  Pre-filled WhatsApp Message:
                </span>
                <p className="text-xs text-slate-200 italic font-sans whitespace-pre-wrap leading-relaxed">
                  "{resultData.message_text}"
                </p>
              </div>

              {/* Action Buttons */}
              <div className="flex flex-col gap-2.5 mt-1">
                {resultData.whatsapp_web_url ? (
                  <a
                    href={resultData.whatsapp_web_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center justify-center gap-2 w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-xl transition-all hover:scale-[1.02] active:scale-95"
                  >
                    <Send className="w-4 h-4" />
                    Open WhatsApp Web Chat (1-Click Send)
                    <ExternalLink className="w-3.5 h-3.5 ml-1 opacity-70" />
                  </a>
                ) : (
                  <div className="p-3 bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs rounded-xl">
                    Patient has no phone number on record. Please download the audio or provide a phone number.
                  </div>
                )}

                <div className="flex items-center gap-2">
                  {resultData.whatsapp_mobile_url && (
                    <a
                      href={resultData.whatsapp_mobile_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex-1 flex items-center justify-center gap-1.5 py-2.5 px-3 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition-colors"
                    >
                      Mobile App (wa.me)
                    </a>
                  )}

                  <a
                    href={resultData.download_url}
                    download
                    className="flex-1 flex items-center justify-center gap-1.5 py-2.5 px-3 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition-colors"
                  >
                    <Download className="w-3.5 h-3.5" />
                    Download Audio
                  </a>
                </div>
              </div>

              <div className="text-[10px] text-slate-500 text-center">
                Note: Opening WhatsApp Web prepares the patient chat with the secure audio link. Click send inside WhatsApp to complete delivery.
              </div>
            </div>
          )}
        </div>

        {/* Modal Footer */}
        {!resultData && (
          <div className="px-6 py-4 border-t border-slate-800 bg-slate-950/60 flex items-center justify-end gap-3">
            <button
              type="button"
              onClick={() => {
                stopAndCleanupStream();
                onClose();
              }}
              className="px-4 py-2 text-xs font-semibold text-slate-400 hover:text-white rounded-xl transition-colors"
            >
              Cancel
            </button>

            <button
              type="button"
              disabled={isSubmitting || (mode === 'record' && !recordedBlob)}
              onClick={handleSubmit}
              className={`flex items-center gap-2 px-5 py-2 text-xs font-bold rounded-xl transition-all ${
                isSubmitting || (mode === 'record' && !recordedBlob)
                  ? 'bg-slate-800 text-slate-500 cursor-not-allowed'
                  : 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg active:scale-95'
              }`}
            >
              {isSubmitting ? (
                <>Preparing Audio...</>
              ) : (
                <>
                  <Send className="w-3.5 h-3.5" />
                  Prepare WhatsApp Link
                </>
              )}
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default VoiceNoteModal;
