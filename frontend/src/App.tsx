import React from 'react';
import Sidebar from './components/Sidebar';
import DomainTable from './components/DomainTable';
import TrendPanel from './components/TrendPanel';
import { DomainItem } from './services/api';

const domains: DomainItem[] = [
  { domain: 'example.com', status: 'OK', pages: 120 },
  { domain: 'domain.pl', status: 'Pending', pages: 80 },
];

const trends = [
  { domain: 'example.com', change: [1, 2, 3, 2, 4] },
  { domain: 'domain.pl', change: [0, 1, 0, 2, 3] },
];

const App: React.FC = () => (
  <div className="flex">
    <Sidebar />
    <main className="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-4 p-4">
      <section className="lg:col-span-2 bg-white rounded-xl shadow-md p-4 overflow-auto">
        <h1 className="text-xl font-semibold mb-4">Domains</h1>
        <DomainTable items={domains} />
      </section>
      <aside className="lg:col-span-1 bg-white rounded-xl shadow-md p-4">
        <h2 className="text-lg font-semibold mb-4">Trends</h2>
        <TrendPanel data={trends} />
      </aside>
      <button className="fixed top-4 right-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl shadow-md">
        Dodaj domenę
      </button>
    </main>
  </div>
);

export default App;
