import { useSyncExternalStore } from "react";
import {
  subscribeAuth,
  getAuthSnapshot,
  loginByCarnet,
  logout,
} from "../services/authService";

/**
 * Estado de la sesión del estudiante (inicio de sesión por carné).
 *
 * Comparte el estado entre todos los componentes que usan el hook y se
 * sincroniza con localStorage.
 */
export const useAuth = () => {
  const snapshot = useSyncExternalStore(
    subscribeAuth,
    getAuthSnapshot,
    getAuthSnapshot
  );

  return {
    token: snapshot.token,
    student: snapshot.student,
    isAuthed: Boolean(snapshot.token),
    login: loginByCarnet,
    logout,
  };
};
