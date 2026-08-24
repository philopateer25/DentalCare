import React, { useMemo, useEffect } from 'react';
import { Canvas } from '@react-three/fiber';
import { OrbitControls, Center, useGLTF } from '@react-three/drei';
import * as THREE from 'three';
import { ToothRecord, CONDITION_COLORS } from './types';
import { extractToothNumber, isSoftTissueNode, isIgnoredNode } from './Dental3DViewer';

interface SingleToothViewerProps {
  modelUrl: string;
  toothNumber: string;
  record?: ToothRecord;
}

const SingleToothMesh: React.FC<SingleToothViewerProps> = ({ modelUrl, toothNumber, record }) => {
  const gltf = useGLTF(modelUrl) as any;
  
  const isolatedMesh = useMemo(() => {
    if (!gltf || !gltf.scene) return null;
    
    let targetMesh: THREE.Mesh | null = null;
    let autoIndex = 1;
    const fdiList = [
      '18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28',
      '48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38',
    ];

    gltf.scene.traverse((child: any) => {
      if (child.isMesh && !isIgnoredNode(child.name) && !isSoftTissueNode(child.name)) {
        let num = extractToothNumber(child.name);
        if (!num) {
          num = fdiList[autoIndex - 1] || String(10 + autoIndex);
          autoIndex++;
        }
        
        if (num === toothNumber && !targetMesh) {
          targetMesh = child.clone(true);
        }
      }
    });

    if (targetMesh) {
      // Setup material based on condition
      const condition = record?.condition || 'healthy';
      const isMissing = condition === 'missing';
      const isImplant = condition === 'implant';
      
      const newMaterial = new THREE.MeshStandardMaterial({
        color: CONDITION_COLORS[condition] || CONDITION_COLORS.healthy,
        roughness: isImplant ? 0.2 : 0.3,
        metalness: isImplant ? 0.85 : 0.05,
        transparent: isMissing,
        opacity: isMissing ? 0.2 : 1.0,
        wireframe: isMissing,
      });
      
      targetMesh.material = newMaterial;
    }
    
    return targetMesh;
  }, [gltf, toothNumber, record?.condition]);

  if (!isolatedMesh) return null;

  return (
    <Center>
      <primitive object={isolatedMesh} />
    </Center>
  );
};

export const SingleToothViewer: React.FC<SingleToothViewerProps> = (props) => {
  return (
    <div className="w-full h-full min-h-[300px] relative rounded-xl overflow-hidden bg-gradient-to-tr from-slate-950 via-slate-900 to-slate-950 border border-slate-700/50 shadow-inner">
      <Canvas camera={{ position: [0, 0, 0.35], fov: 15 }} shadows>
        <ambientLight intensity={1.2} />
        <directionalLight position={[5, 5, 5]} intensity={2} castShadow />
        <directionalLight position={[-5, -5, -5]} intensity={0.5} />
        
        <SingleToothMesh {...props} />
        
        <OrbitControls 
          enablePan={false} 
          minDistance={0.05} 
          maxDistance={3} 
          autoRotate 
          autoRotateSpeed={3}
          makeDefault 
        />
      </Canvas>
      <div className="absolute top-2 left-3 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
        Isolated 3D View
      </div>
    </div>
  );
};
