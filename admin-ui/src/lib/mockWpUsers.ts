import type { WpRestUser } from '../types';

export const MOCK_STUDENTS: WpRestUser[] = [
  {
    id: 88001,
    name: 'Alex Rivera',
    slug: 'alex-rivera',
    email: 'alex@example.com',
    registered_date: '2025-01-10T12:00:00',
  },
  {
    id: 88002,
    name: 'Jordan Lee',
    slug: 'jordan-lee',
    email: 'jordan@example.com',
    registered_date: '2025-02-03T09:30:00',
  },
  {
    id: 88003,
    name: 'Sam Patel',
    slug: 'sam-patel',
    email: 'sam@example.com',
    registered_date: '2025-03-01T16:00:00',
  },
];

export const MOCK_INSTRUCTORS: WpRestUser[] = [
  {
    id: 88011,
    name: 'Dr. Morgan Chen',
    slug: 'morgan-chen',
    email: 'morgan@example.com',
    registered_date: '2024-06-15T10:00:00',
  },
  {
    id: 88012,
    name: 'Riley Brooks',
    slug: 'riley-brooks',
    email: 'riley@example.com',
    registered_date: '2024-09-20T14:00:00',
  },
];
