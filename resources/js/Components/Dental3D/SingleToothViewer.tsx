import React, { useMemo, useState } from 'react';
import { Canvas } from '@react-three/fiber';
import { OrbitControls, Center, useGLTF } from '@react-three/drei';
import * as THREE from 'three';
import { ToothRecord, CONDITION_COLORS, SURFACE_CONDITION_COLORS, SurfaceCondition } from './types';
import { parseDentitionMesh, TEETH_MANIFEST, ToothSurfaceKey } from './dentitionManifest';
import { Sparkles, RotateCw } from 'lucide-react';

interface SingleToothViewerProps {
  modelUrl?: string;
  toothNumber: string;
  record?: ToothRecord;
}

const SingleToothMesh: React.FC<SingleToothViewerProps & { xrayMode: boolean }> = ({
  modelUrl = '/models/Dentition_Permanent_Full_V2.1.glb',
  toothNumber,
  record,
  xrayMode,
}) => {
  const gltf = useGLTF(modelUrl) as any;

  const isolatedMesh = useMemo(() => {
    if (!gltf || !gltf.scene) return null;

    const toothGroup = new THREE.Group();
    const condition = record?.condition || 'healthy';
    const isMissing = condition === 'missing';
    const isImplant = condition === 'implant';
    const isCrown = condition === 'crown';

    // Traverse the scene and find all parts for this tooth
    gltf.scene.traverse((child: any) => {
      if (child.isMesh) {
        const dental = parseDentitionMesh(child.name);
        if (dental && dental.toothNumber === toothNumber) {
          const clonedMesh = child.clone(true) as THREE.Mesh;
          const mat = child.material ? (child.material as THREE.MeshStandardMaterial).clone() : new THREE.MeshStandardMaterial();

          // Submesh specific styling
          if (dental.isPulp) {
            if (condition === 'root_canal') {
              mat.color.set('#8B5CF6');
              mat.emissive.set('#8B5CF6');
              mat.emissiveIntensity = 1.3;
            } else {
              mat.color.set('#E11D48');
              mat.emissive.set('#BE123C');
              mat.emissiveIntensity = 0.9;
            }
            clonedMesh.visible = xrayMode;
          } else if (dental.isDentin) {
            if (xrayMode) {
              mat.transparent = true;
              mat.opacity = 0.4;
              mat.depthWrite = false;
            }
            clonedMesh.visible = xrayMode;
          } else if (dental.isRoot) {
            if (isImplant) {
              mat.color.set('#06B6D4');
              mat.metalness = 0.85;
              mat.roughness = 0.2;
            } else if (isMissing) {
              mat.color.set('#475569');
              mat.wireframe = true;
              mat.transparent = true;
              mat.opacity = 0.18;
            } else if (xrayMode) {
              mat.transparent = true;
              mat.opacity = 0.35;
              mat.depthWrite = false;
            }
            clonedMesh.visible = true;
          } else if (dental.isCrown) {
            if (isMissing) {
              mat.color.set('#475569');
              mat.wireframe = true;
              mat.transparent = true;
              mat.opacity = 0.18;
            } else if (isImplant) {
              mat.color.set('#06B6D4');
              mat.metalness = 0.85;
              mat.roughness = 0.2;
            } else if (isCrown) {
              mat.color.set('#F59E0B');
              mat.metalness = 0.35;
              mat.roughness = 0.22;
            } else {
              const sKey = dental.surfaceKey;
              const sCond: SurfaceCondition = sKey && record?.surfaces?.[sKey]
                ? record.surfaces[sKey]!
                : 'healthy';

              if (sCond !== 'healthy') {
                mat.color.set(SURFACE_CONDITION_COLORS[sCond] || mat.color);
                if (sCond === 'amalgam') {
                  mat.roughness = 0.28;
                  mat.metalness = 0.75;
                }
              } else if (condition === 'active_caries') {
                mat.color.set(CONDITION_COLORS.active_caries);
              } else if (condition === 'composite_filled') {
                mat.color.set(CONDITION_COLORS.composite_filled);
              }
              // If healthy, keeps exact authored GLTF appearance

              if (xrayMode) {
                mat.transparent = true;
                mat.opacity = 0.26;
                mat.depthWrite = false;
                mat.emissive.set('#93C5FD');
                mat.emissiveIntensity = 0.25;
              }
            }
            clonedMesh.visible = true;
          }

          clonedMesh.material = mat;
          clonedMesh.castShadow = true;
          clonedMesh.receiveShadow = true;
          toothGroup.add(clonedMesh);
        }
      }
    });

    if (toothGroup.children.length === 0) return null;

    // Center tooth geometry around local origin
    const box = new THREE.Box3().setFromObject(toothGroup);
    const center = new THREE.Vector3();
    box.getCenter(center);
    toothGroup.position.sub(center);

    return toothGroup;
  }, [gltf, toothNumber, record, xrayMode]);

  if (!isolatedMesh) return null;

  return <primitive object={isolatedMesh} />;
};

export const SingleToothViewer: React.FC<SingleToothViewerProps> = (props) => {
  const [xrayMode, setXrayMode] = useState(false);
  const [autoRotate, setAutoRotate] = useState(true);

  const manifest = TEETH_MANIFEST[props.toothNumber];

  return (
    <div className="w-full h-full min-h-[340px] relative rounded-2xl overflow-hidden bg-gradient-to-tr from-slate-950 via-slate-900 to-slate-950 border border-slate-700/60 shadow-2xl flex flex-col">
      <Canvas camera={{ position: [0, 0, 5.0], fov: 28 }} shadows className="w-full flex-1">
        <hemisphereLight args={['#ffffff', '#1e293b', 1.2]} />
        <directionalLight position={[4, 6, 6]} intensity={2.2} castShadow />
        <directionalLight position={[-4, -3, 4]} intensity={1.2} />
        <directionalLight position={[0, -5, -4]} intensity={0.8} />

        <Center>
          <SingleToothMesh {...props} xrayMode={xrayMode} />
        </Center>

        <OrbitControls
          enablePan={false}
          minDistance={1.8}
          maxDistance={9}
          autoRotate={autoRotate}
          autoRotateSpeed={2.5}
          makeDefault
        />
      </Canvas>

      {/* Top Overlay Badges */}
      <div className="absolute top-3 left-3 flex flex-col gap-0.5 pointer-events-none">
        <div className="text-[10px] uppercase font-bold text-emerald-400 tracking-wider">
          Isolated Anatomical 3D Inspector
        </div>
        {manifest && (
          <div className="text-[11px] text-slate-300 font-medium">
            {manifest.clinicalName} ({manifest.universalNumber})
          </div>
        )}
      </div>

      {/* Bottom Floating Control Buttons */}
      <div className="absolute bottom-3 right-3 flex items-center gap-1.5 z-20">
        <button
          type="button"
          onClick={() => setXrayMode(!xrayMode)}
          className={`flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-lg border backdrop-blur-md shadow transition-all ${
            xrayMode
              ? 'bg-sky-500 text-white border-sky-300 ring-2 ring-sky-400/40'
              : 'bg-slate-900/85 hover:bg-slate-800 text-slate-300 border-slate-700'
          }`}
          title="Toggle X-Ray / Pulp & Root Canal View"
        >
          <Sparkles className="w-3 h-3 text-sky-400" />
          <span>{xrayMode ? 'Pulp Visible' : 'X-Ray'}</span>
        </button>

        <button
          type="button"
          onClick={() => setAutoRotate(!autoRotate)}
          className={`p-1.5 rounded-lg border backdrop-blur-md shadow transition-all ${
            autoRotate
              ? 'bg-emerald-600/90 text-white border-emerald-400'
              : 'bg-slate-900/85 hover:bg-slate-800 text-slate-300 border-slate-700'
          }`}
          title={autoRotate ? 'Pause Rotation' : 'Auto Rotate'}
        >
          <RotateCw className={`w-3.5 h-3.5 ${autoRotate ? 'animate-spin' : ''}`} style={{ animationDuration: '6s' }} />
        </button>
      </div>
    </div>
  );
};

export default SingleToothViewer;
