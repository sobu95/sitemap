import React from 'react';
import TableRow from './TableRow';

interface Domain {
  domain: string;
  status: string;
  pages: number;
}

interface Props {
  items: Domain[];
}

const DomainTable: React.FC<Props> = ({ items }) => (
  <table className="min-w-full text-sm">
    <thead>
      <tr className="text-left">
        <th className="px-4 py-2">Domain</th>
        <th className="px-4 py-2 text-center">Status</th>
        <th className="px-4 py-2 text-right">Pages</th>
      </tr>
    </thead>
    <tbody>
      {items.map((d) => (
        <TableRow key={d.domain} domain={d.domain} status={d.status} pages={d.pages} />
      ))}
    </tbody>
  </table>
);

export default DomainTable;
