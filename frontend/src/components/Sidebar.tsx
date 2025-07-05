import React from 'react';

const items = [
  'Dashboard',
  'Domeny',
  'Historia',
  'Ustawienia',
];

const Sidebar: React.FC = () => (
  <aside className="bg-gray-800 text-white flex flex-col items-center sm:items-start py-4 w-16 sm:w-48 min-h-screen space-y-4">
    {items.map((item) => (
      <div key={item} className="flex items-center px-2 sm:px-4">
        <span className="material-icons mr-0 sm:mr-2 text-lg">dashboard</span>
        <span className="hidden sm:inline-block">{item}</span>
      </div>
    ))}
  </aside>
);

export default Sidebar;
