// Dentition Metadata & Mesh Parser for Dentition_Permanent_Full_V2.1.glb
// Based on DENT-3D-SPEC-2026-V2.1

export type AnatomicalType =
  | 'CentralIncisor'
  | 'LateralIncisor'
  | 'Canine'
  | 'Premolar1'
  | 'Premolar2'
  | 'Molar1'
  | 'Molar2'
  | 'Molar3';

export type ArchType = 'Maxillary' | 'Mandibular';
export type QuadrantType = 'UR' | 'UL' | 'LR' | 'LL';

export type ToothSurfaceKey = 'occlusal' | 'mesial' | 'distal' | 'buccal' | 'lingual' | 'cervical';

export interface ToothManifestEntry {
  universalNumber: string; // e.g. '#01'
  fdiNumber: string;       // e.g. '18'
  prefix: string;          // e.g. 'T01'
  arch: ArchType;
  quadrant: QuadrantType;
  anatomicalType: AnatomicalType;
  clinicalName: string;
  sceneNodeName: string;
  cejPivot: [number, number, number];
}

// 32 Discrete Permanent Teeth Registry
export const TEETH_MANIFEST: Record<string, ToothManifestEntry> = {
  '18': { universalNumber: '#01', fdiNumber: '18', prefix: 'T01', arch: 'Maxillary', quadrant: 'UR', anatomicalType: 'Molar3', clinicalName: 'Maxillary Right 3rd Molar (Wisdom)', sceneNodeName: 'Tooth_UN01_FDI18_UR_Molar3', cejPivot: [-29.213, 8.2, -17.01] },
  '17': { universalNumber: '#02', fdiNumber: '17', prefix: 'T02', arch: 'Maxillary', quadrant: 'UR', anatomicalType: 'Molar2', clinicalName: 'Maxillary Right 2nd Molar (12-Yr)', sceneNodeName: 'Tooth_UN02_FDI17_UR_Molar2', cejPivot: [-29.409, 8.2, -9.175] },
  '16': { universalNumber: '#03', fdiNumber: '16', prefix: 'T03', arch: 'Maxillary', quadrant: 'UR', anatomicalType: 'Molar1', clinicalName: 'Maxillary Right 1st Molar (6-Yr)', sceneNodeName: 'Tooth_UN03_FDI16_UR_Molar1', cejPivot: [-27.976, 8.2, -0.577] },
  '15': { universalNumber: '#04', fdiNumber: '15', prefix: 'T04', arch: 'Maxillary', quadrant: 'UR', anatomicalType: 'Premolar2', clinicalName: 'Maxillary Right 2nd Premolar', sceneNodeName: 'Tooth_UN04_FDI15_UR_Premolar2', cejPivot: [-24.882, 8.2, 7.332] },
  '14': { universalNumber: '#05', fdiNumber: '14', prefix: 'T05', arch: 'Maxillary', quadrant: 'UR', anatomicalType: 'Premolar1', clinicalName: 'Maxillary Right 1st Premolar', sceneNodeName: 'Tooth_UN05_FDI14_UR_Premolar1', cejPivot: [-20.68, 8.2, 13.673] },
  '13': { universalNumber: '#06', fdiNumber: '13', prefix: 'T06', arch: 'Maxillary', quadrant: 'UR', anatomicalType: 'Canine', clinicalName: 'Maxillary Right Canine (Cuspid)', sceneNodeName: 'Tooth_UN06_FDI13_UR_Canine', cejPivot: [-15.193, 8.2, 18.88] },
  '12': { universalNumber: '#07', fdiNumber: '12', prefix: 'T07', arch: 'Maxillary', quadrant: 'UR', anatomicalType: 'LateralIncisor', clinicalName: 'Maxillary Right Lateral Incisor', sceneNodeName: 'Tooth_UN07_FDI12_UR_LateralIncisor', cejPivot: [-9.356, 8.2, 22.18] },
  '11': { universalNumber: '#08', fdiNumber: '11', prefix: 'T08', arch: 'Maxillary', quadrant: 'UR', anatomicalType: 'CentralIncisor', clinicalName: 'Maxillary Right Central Incisor', sceneNodeName: 'Tooth_UN08_FDI11_UR_CentralIncisor', cejPivot: [-3.336, 8.2, 23.82] },

  '21': { universalNumber: '#09', fdiNumber: '21', prefix: 'T09', arch: 'Maxillary', quadrant: 'UL', anatomicalType: 'CentralIncisor', clinicalName: 'Maxillary Left Central Incisor', sceneNodeName: 'Tooth_UN09_FDI21_UL_CentralIncisor', cejPivot: [3.336, 8.2, 23.82] },
  '22': { universalNumber: '#10', fdiNumber: '22', prefix: 'T10', arch: 'Maxillary', quadrant: 'UL', anatomicalType: 'LateralIncisor', clinicalName: 'Maxillary Left Lateral Incisor', sceneNodeName: 'Tooth_UN10_FDI22_UL_LateralIncisor', cejPivot: [9.356, 8.2, 22.18] },
  '23': { universalNumber: '#11', fdiNumber: '23', prefix: 'T11', arch: 'Maxillary', quadrant: 'UL', anatomicalType: 'Canine', clinicalName: 'Maxillary Left Canine (Cuspid)', sceneNodeName: 'Tooth_UN11_FDI23_UL_Canine', cejPivot: [15.193, 8.2, 18.88] },
  '24': { universalNumber: '#12', fdiNumber: '24', prefix: 'T12', arch: 'Maxillary', quadrant: 'UL', anatomicalType: 'Premolar1', clinicalName: 'Maxillary Left 1st Premolar', sceneNodeName: 'Tooth_UN12_FDI24_UL_Premolar1', cejPivot: [20.68, 8.2, 13.673] },
  '25': { universalNumber: '#13', fdiNumber: '25', prefix: 'T13', arch: 'Maxillary', quadrant: 'UL', anatomicalType: 'Premolar2', clinicalName: 'Maxillary Left 2nd Premolar', sceneNodeName: 'Tooth_UN13_FDI25_UL_Premolar2', cejPivot: [24.882, 8.2, 7.332] },
  '26': { universalNumber: '#14', fdiNumber: '26', prefix: 'T14', arch: 'Maxillary', quadrant: 'UL', anatomicalType: 'Molar1', clinicalName: 'Maxillary Left 1st Molar (6-Yr)', sceneNodeName: 'Tooth_UN14_FDI26_UL_Molar1', cejPivot: [27.976, 8.2, -0.577] },
  '27': { universalNumber: '#15', fdiNumber: '27', prefix: 'T15', arch: 'Maxillary', quadrant: 'UL', anatomicalType: 'Molar2', clinicalName: 'Maxillary Left 2nd Molar (12-Yr)', sceneNodeName: 'Tooth_UN15_FDI27_UL_Molar2', cejPivot: [29.409, 8.2, -9.175] },
  '28': { universalNumber: '#16', fdiNumber: '28', prefix: 'T16', arch: 'Maxillary', quadrant: 'UL', anatomicalType: 'Molar3', clinicalName: 'Maxillary Left 3rd Molar (Wisdom)', sceneNodeName: 'Tooth_UN16_FDI28_UL_Molar3', cejPivot: [29.213, 8.2, -17.01] },

  '38': { universalNumber: '#17', fdiNumber: '38', prefix: 'T17', arch: 'Mandibular', quadrant: 'LL', anatomicalType: 'Molar3', clinicalName: 'Mandibular Left 3rd Molar (Wisdom)', sceneNodeName: 'Tooth_UN17_FDI38_LL_Molar3', cejPivot: [26.938, -8.2, -16.53] },
  '37': { universalNumber: '#18', fdiNumber: '37', prefix: 'T18', arch: 'Mandibular', quadrant: 'LL', anatomicalType: 'Molar2', clinicalName: 'Mandibular Left 2nd Molar (12-Yr)', sceneNodeName: 'Tooth_UN18_FDI37_LL_Molar2', cejPivot: [27.118, -8.2, -9.215] },
  '36': { universalNumber: '#19', fdiNumber: '36', prefix: 'T19', arch: 'Mandibular', quadrant: 'LL', anatomicalType: 'Molar1', clinicalName: 'Mandibular Left 1st Molar (6-Yr)', sceneNodeName: 'Tooth_UN19_FDI36_LL_Molar1', cejPivot: [25.794, -8.2, -1.21] },
  '35': { universalNumber: '#20', fdiNumber: '35', prefix: 'T20', arch: 'Mandibular', quadrant: 'LL', anatomicalType: 'Premolar2', clinicalName: 'Mandibular Left 2nd Premolar', sceneNodeName: 'Tooth_UN20_FDI35_LL_Premolar2', cejPivot: [22.937, -8.2, 6.182] },
  '34': { universalNumber: '#21', fdiNumber: '34', prefix: 'T21', arch: 'Mandibular', quadrant: 'LL', anatomicalType: 'Premolar1', clinicalName: 'Mandibular Left 1st Premolar', sceneNodeName: 'Tooth_UN21_FDI34_LL_Premolar1', cejPivot: [19.064, -8.2, 12.08] },
  '33': { universalNumber: '#22', fdiNumber: '33', prefix: 'T22', arch: 'Mandibular', quadrant: 'LL', anatomicalType: 'Canine', clinicalName: 'Mandibular Left Canine', sceneNodeName: 'Tooth_UN22_FDI33_LL_Canine', cejPivot: [14.009, -8.2, 16.92] },
  '32': { universalNumber: '#23', fdiNumber: '32', prefix: 'T23', arch: 'Mandibular', quadrant: 'LL', anatomicalType: 'LateralIncisor', clinicalName: 'Mandibular Left Lateral Incisor', sceneNodeName: 'Tooth_UN23_FDI32_LL_LateralIncisor', cejPivot: [8.627, -8.2, 19.96] },
  '31': { universalNumber: '#24', fdiNumber: '31', prefix: 'T24', arch: 'Mandibular', quadrant: 'LL', anatomicalType: 'CentralIncisor', clinicalName: 'Mandibular Left Central Incisor', sceneNodeName: 'Tooth_UN24_FDI31_LL_CentralIncisor', cejPivot: [3.078, -8.2, 21.48] },

  '41': { universalNumber: '#25', fdiNumber: '41', prefix: 'T25', arch: 'Mandibular', quadrant: 'LR', anatomicalType: 'CentralIncisor', clinicalName: 'Mandibular Right Central Incisor', sceneNodeName: 'Tooth_UN25_FDI41_LR_CentralIncisor', cejPivot: [-3.078, -8.2, 21.48] },
  '42': { universalNumber: '#26', fdiNumber: '42', prefix: 'T26', arch: 'Mandibular', quadrant: 'LR', anatomicalType: 'LateralIncisor', clinicalName: 'Mandibular Right Lateral Incisor', sceneNodeName: 'Tooth_UN26_FDI42_LR_LateralIncisor', cejPivot: [-8.627, -8.2, 19.96] },
  '43': { universalNumber: '#27', fdiNumber: '43', prefix: 'T27', arch: 'Mandibular', quadrant: 'LR', anatomicalType: 'Canine', clinicalName: 'Mandibular Right Canine', sceneNodeName: 'Tooth_UN27_FDI43_LR_Canine', cejPivot: [-14.009, -8.2, 16.92] },
  '44': { universalNumber: '#28', fdiNumber: '44', prefix: 'T28', arch: 'Mandibular', quadrant: 'LR', anatomicalType: 'Premolar1', clinicalName: 'Mandibular Right 1st Premolar', sceneNodeName: 'Tooth_UN28_FDI44_LR_Premolar1', cejPivot: [-19.064, -8.2, 12.08] },
  '45': { universalNumber: '#29', fdiNumber: '45', prefix: 'T29', arch: 'Mandibular', quadrant: 'LR', anatomicalType: 'Premolar2', clinicalName: 'Mandibular Right 2nd Premolar', sceneNodeName: 'Tooth_UN29_FDI45_LR_Premolar2', cejPivot: [-22.937, -8.2, 6.182] },
  '46': { universalNumber: '#30', fdiNumber: '46', prefix: 'T30', arch: 'Mandibular', quadrant: 'LR', anatomicalType: 'Molar1', clinicalName: 'Mandibular Right 1st Molar (6-Yr)', sceneNodeName: 'Tooth_UN30_FDI46_LR_Molar1', cejPivot: [-25.794, -8.2, -1.21] },
  '47': { universalNumber: '#31', fdiNumber: '47', prefix: 'T31', arch: 'Mandibular', quadrant: 'LR', anatomicalType: 'Molar2', clinicalName: 'Mandibular Right 2nd Molar (12-Yr)', sceneNodeName: 'Tooth_UN31_FDI47_LR_Molar2', cejPivot: [-27.118, -8.2, -9.215] },
  '48': { universalNumber: '#32', fdiNumber: '48', prefix: 'T32', arch: 'Mandibular', quadrant: 'LR', anatomicalType: 'Molar3', clinicalName: 'Mandibular Right 3rd Molar (Wisdom)', sceneNodeName: 'Tooth_UN32_FDI48_LR_Molar3', cejPivot: [-26.938, -8.2, -16.53] },
};

// Map prefix (e.g. 'T01') to FDI number ('18')
export const PREFIX_TO_FDI: Record<string, string> = Object.values(TEETH_MANIFEST).reduce(
  (acc, item) => {
    acc[item.prefix] = item.fdiNumber;
    return acc;
  },
  {} as Record<string, string>
);

// Map Universal Number (e.g. '#01') to FDI number ('18')
export const UNIVERSAL_TO_FDI: Record<string, string> = Object.values(TEETH_MANIFEST).reduce(
  (acc, item) => {
    acc[item.universalNumber] = item.fdiNumber;
    return acc;
  },
  {} as Record<string, string>
);

export type MeshPartType = 'crown_surface' | 'root' | 'dentin' | 'pulp' | 'tooth_group' | 'other';

export interface ParsedMeshInfo {
  toothNumber: string;        // FDI Number, e.g. '18'
  universalNumber: string;    // Universal Number, e.g. '#01'
  clinicalName: string;
  arch: ArchType;
  quadrant: QuadrantType;
  partType: MeshPartType;
  surfaceKey?: ToothSurfaceKey;
  surfaceLabel?: string;
  rootName?: string;
  isCrown: boolean;
  isRoot: boolean;
  isDentin: boolean;
  isPulp: boolean;
}

/**
 * Parses any node or mesh name from the GLB scene into structured dental metadata.
 * Handles prefixes (T01_Srf_OCC), node names (Tooth_UN01_FDI18_UR_Molar3), and legacy names.
 */
export function parseDentitionMesh(name: string): ParsedMeshInfo | null {
  if (!name) return null;

  // 1. Direct Tooth Group Node match: e.g. Tooth_UN01_FDI18_UR_Molar3
  const toothGroupMatch = name.match(/Tooth_UN\d+_FDI(\d+)/i);
  if (toothGroupMatch) {
    const fdi = toothGroupMatch[1];
    const manifest = TEETH_MANIFEST[fdi];
    if (manifest) {
      return {
        toothNumber: fdi,
        universalNumber: manifest.universalNumber,
        clinicalName: manifest.clinicalName,
        arch: manifest.arch,
        quadrant: manifest.quadrant,
        partType: 'tooth_group',
        isCrown: false,
        isRoot: false,
        isDentin: false,
        isPulp: false,
      };
    }
  }

  // 2. Submesh prefix match: e.g. T01_Srf_OCC, T01_Root_Palatal, T01_Core_Dentin, T01_Cavity_Pulp
  const prefixMatch = name.match(/^(T\d\d)_(.+)$/i);
  if (prefixMatch) {
    const prefix = prefixMatch[1].toUpperCase();
    const rest = prefixMatch[2];
    const fdi = PREFIX_TO_FDI[prefix];
    if (fdi && TEETH_MANIFEST[fdi]) {
      const manifest = TEETH_MANIFEST[fdi];

      // Crown Surfaces
      if (rest.startsWith('Srf_')) {
        const srfSuffix = rest.replace('Srf_', '').toUpperCase();
        let surfaceKey: ToothSurfaceKey = 'occlusal';
        let surfaceLabel = 'Occlusal';

        switch (srfSuffix) {
          case 'OCC':
            surfaceKey = 'occlusal';
            surfaceLabel = 'Occlusal';
            break;
          case 'INC':
            surfaceKey = 'occlusal';
            surfaceLabel = 'Incisal';
            break;
          case 'MES':
            surfaceKey = 'mesial';
            surfaceLabel = 'Mesial';
            break;
          case 'DIS':
            surfaceKey = 'distal';
            surfaceLabel = 'Distal';
            break;
          case 'BUC':
            surfaceKey = 'buccal';
            surfaceLabel = 'Buccal';
            break;
          case 'LAB':
            surfaceKey = 'buccal';
            surfaceLabel = 'Labial / Facial';
            break;
          case 'PAL':
            surfaceKey = 'lingual';
            surfaceLabel = 'Palatal (Lingual)';
            break;
          case 'LIN':
            surfaceKey = 'lingual';
            surfaceLabel = 'Lingual';
            break;
          case 'CRV':
            surfaceKey = 'cervical';
            surfaceLabel = 'Cervical Margin';
            break;
          default:
            surfaceKey = 'occlusal';
            surfaceLabel = srfSuffix;
        }

        return {
          toothNumber: fdi,
          universalNumber: manifest.universalNumber,
          clinicalName: manifest.clinicalName,
          arch: manifest.arch,
          quadrant: manifest.quadrant,
          partType: 'crown_surface',
          surfaceKey,
          surfaceLabel,
          isCrown: true,
          isRoot: false,
          isDentin: false,
          isPulp: false,
        };
      }

      // Root Submeshes
      if (rest.startsWith('Root_') || rest === 'Roots') {
        const rootBranch = rest.replace('Root_', '');
        return {
          toothNumber: fdi,
          universalNumber: manifest.universalNumber,
          clinicalName: manifest.clinicalName,
          arch: manifest.arch,
          quadrant: manifest.quadrant,
          partType: 'root',
          rootName: rootBranch,
          isCrown: false,
          isRoot: true,
          isDentin: false,
          isPulp: false,
        };
      }

      // Dentin Core
      if (rest.includes('Dentin')) {
        return {
          toothNumber: fdi,
          universalNumber: manifest.universalNumber,
          clinicalName: manifest.clinicalName,
          arch: manifest.arch,
          quadrant: manifest.quadrant,
          partType: 'dentin',
          isCrown: false,
          isRoot: false,
          isDentin: true,
          isPulp: false,
        };
      }

      // Pulp Chamber & Root Canal
      if (rest.includes('Pulp')) {
        return {
          toothNumber: fdi,
          universalNumber: manifest.universalNumber,
          clinicalName: manifest.clinicalName,
          arch: manifest.arch,
          quadrant: manifest.quadrant,
          partType: 'pulp',
          isCrown: false,
          isRoot: false,
          isDentin: false,
          isPulp: true,
        };
      }
    }
  }

  // 3. Fallback: match any FDI number in the node string
  const generalFdiMatch = name.match(/\b([1-4][1-8])\b/);
  if (generalFdiMatch) {
    const fdi = generalFdiMatch[1];
    const manifest = TEETH_MANIFEST[fdi];
    if (manifest) {
      return {
        toothNumber: fdi,
        universalNumber: manifest.universalNumber,
        clinicalName: manifest.clinicalName,
        arch: manifest.arch,
        quadrant: manifest.quadrant,
        partType: 'other',
        isCrown: true,
        isRoot: false,
        isDentin: false,
        isPulp: false,
      };
    }
  }

  return null;
}
